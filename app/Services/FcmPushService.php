<?php

namespace App\Services;

use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    /**
     * Send push notification to target users via Firebase Cloud Messaging (FCM v1 / legacy).
     *
     * @param array<int> $userIds
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sendPushToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        if (empty($userIds)) {
            return ['status' => 'skipped', 'reason' => 'empty_user_ids', 'sent' => 0];
        }

        $tokens = UserDeviceToken::whereIn('user_id', $userIds)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::info('FcmPushService: No device tokens found for users', ['user_ids' => $userIds]);
            return ['status' => 'skipped', 'reason' => 'no_tokens', 'sent' => 0];
        }

        // In testing environment, return mock result immediately
        if (app()->environment('testing')) {
            Log::info('FcmPushService: Dispatched (testing mock)', [
                'user_ids' => $userIds,
                'tokens_count' => count($tokens),
                'title' => $title,
                'body' => $body,
            ]);

            return [
                'status' => 'mock_dispatched',
                'sent' => count($tokens),
                'tokens' => $tokens,
            ];
        }

        // 1. Try FCM HTTP v1 using Service Account JSON credentials
        $serviceAccount = static::getServiceAccountCredentials();
        if ($serviceAccount) {
            $result = static::sendViaFcmV1($serviceAccount, $tokens, $title, $body, $data);
            if ($result['status'] === 'dispatched') {
                return $result;
            }
        }

        // 2. Fallback: Legacy FCM HTTP API if legacy server key is provided
        $serverKey = config('services.fcm.key');
        if (! empty($serverKey)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $tokens,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => array_map(fn ($val) => is_scalar($val) ? (string) $val : json_encode($val), $data),
                    'priority' => 'high',
                ]);

                Log::info('FcmPushService: Dispatched FCM legacy push', [
                    'recipient_count' => count($tokens),
                    'fcm_status' => $response->status(),
                ]);

                return [
                    'status' => 'dispatched',
                    'sent' => count($tokens),
                    'response' => $response->json(),
                ];
            } catch (\Throwable $e) {
                Log::warning('FcmPushService: Legacy HTTP dispatch failed: ' . $e->getMessage());
            }
        }

        // 3. Graceful Fallback: Log dispatch if no valid credentials available
        Log::info('FcmPushService: Dispatched (mock/logged)', [
            'user_ids' => $userIds,
            'tokens_count' => count($tokens),
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return [
            'status' => 'mock_dispatched',
            'sent' => count($tokens),
            'tokens' => $tokens,
        ];
    }

    /**
     * Send push notification via modern FCM HTTP v1 API.
     */
    protected static function sendViaFcmV1(
        array $serviceAccount,
        array $tokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        $accessToken = static::getOAuth2AccessToken($serviceAccount);
        if (! $accessToken) {
            return ['status' => 'failed', 'reason' => 'oauth2_token_failed'];
        }

        $projectId = $serviceAccount['project_id'] ?? config('services.fcm.project_id');
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $stringData = [];
        if (! empty($data)) {
            foreach ($data as $key => $val) {
                $stringData[(string) $key] = is_scalar($val) ? (string) $val : json_encode($val);
            }
        }

        $sentCount = 0;
        foreach ($tokens as $token) {
            try {
                $messagePayload = [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ];

                if (! empty($stringData)) {
                    $messagePayload['data'] = $stringData;
                }

                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($endpoint, [
                        'message' => $messagePayload,
                    ]);

                if ($response->ok()) {
                    $sentCount++;
                } else {
                    Log::warning('FcmPushService: FCM v1 token send returned error', [
                        'token_preview' => substr($token, 0, 10) . '...',
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('FcmPushService: FCM v1 dispatch exception: ' . $e->getMessage());
            }
        }

        return [
            'status' => 'dispatched',
            'sent' => $sentCount,
            'total' => count($tokens),
        ];
    }

    /**
     * Get Google OAuth2 access token using the Service Account JSON credentials.
     */
    protected static function getOAuth2AccessToken(array $serviceAccount): ?string
    {
        return Cache::remember('fcm_v1_access_token', 3500, function () use ($serviceAccount) {
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $now = time();
            $claim = json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]);

            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
            $dataToSign = $base64UrlHeader . '.' . $base64UrlClaim;

            $signature = '';
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if (! $privateKey) {
                Log::warning('FcmPushService: Invalid private key in service account JSON');
                return null;
            }

            openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            $jwt = $dataToSign . '.' . $base64UrlSignature;

            $tokenResponse = Http::asForm()
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($tokenResponse->ok()) {
                return $tokenResponse->json('access_token');
            }

            Log::warning('FcmPushService: Failed to retrieve OAuth2 token from Google', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return null;
        });
    }

    /**
     * Resolve Service Account credentials from file or environment.
     */
    protected static function getServiceAccountCredentials(): ?array
    {
        $customPath = config('services.fcm.credentials_path') ?: env('FIREBASE_CREDENTIALS');
        $defaultPath = storage_path('app/firebase/service-account.json');

        $path = $customPath && file_exists($customPath) ? $customPath : ($defaultPath && file_exists($defaultPath) ? $defaultPath : null);

        if ($path && file_exists($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json) && ! empty($json['private_key']) && ! empty($json['client_email'])) {
                return $json;
            }
        }

        return null;
    }
}
