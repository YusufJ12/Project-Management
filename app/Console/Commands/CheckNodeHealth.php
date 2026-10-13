<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckNodeHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:check-node-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deteksi node VPS klien yang tidak mengirim heartbeat lebih dari 15 menit atau mengalami beban kritis';

    public function handle(): int
    {
        $this->info('Memulai pengecekan kesehatan node VPS klien...');

        // Scan seluruh tenant aktif/trial yang memiliki node terdaftar
        $tenants = Tenant::with(['vault', 'latestTelemetry'])
            ->whereIn('status', ['active', 'trial'])
            ->get();

        $offlineCount = 0;
        $warningCount = 0;

        foreach ($tenants as $tenant) {
            $tele = $tenant->latestTelemetry;
            $ip = $tenant->vault?->vps_ip_address ?? 'IP belum diset';

            // Kasus 1: Belum pernah kirim heartbeat sama sekali
            if (! $tele || ! $tele->reported_at) {
                continue;
            }

            $diffMinutes = $tele->reported_at->diffInMinutes(now());

            // Kasus 2: Node mati / tidak kirim heartbeat > 15 menit
            if ($diffMinutes > 15) {
                $offlineCount++;

                // Cegah spam alert: cek apakah sudah dicatat dalam 1 jam terakhir
                $alreadyAlerted = AuditLog::where('action', 'node_offline_alert')
                    ->where('target_id', $tenant->id)
                    ->where('created_at', '>=', now()->subHour())
                    ->exists();

                if (! $alreadyAlerted) {
                    $lastReported = $tele->reported_at->format('d M Y H:i:s');
                    $msg = "Peringatan: Node VPS [{$tenant->company_name}] ({$ip}) tidak mengirim heartbeat selama {$diffMinutes} menit (lebih dari 15 menit). Terakhir lapor: {$lastReported}. Periksa koneksi server.";

                    AuditLog::log(
                        'node_offline_alert',
                        'Tenant',
                        $tenant->id,
                        $msg
                    );

                    $this->error(" [DEAD NODE] {$msg}");
                }

                continue;
            }

            // Kasus 3: Node overload (RAM > 85% atau CPU > 85%)
            if (($tele->ram_usage_percent ?? 0) > 85 || ($tele->cpu_usage_percent ?? 0) > 85) {
                $warningCount++;

                $alreadyAlerted = AuditLog::where('action', 'node_high_resource_alert')
                    ->where('target_id', $tenant->id)
                    ->where('created_at', '>=', now()->subHour())
                    ->exists();

                if (! $alreadyAlerted) {
                    $msg = "Peringatan: Node VPS [{$tenant->company_name}] ({$ip}) mengalami beban tinggi: RAM {$tele->ram_usage_percent}%, CPU {$tele->cpu_usage_percent}%.";

                    AuditLog::log(
                        'node_high_resource_alert',
                        'Tenant',
                        $tenant->id,
                        $msg
                    );

                    $this->warn(" [HIGH LOAD] {$msg}");
                }
            }
        }

        $this->info("Pengecekan selesai. Ditemukan {$offlineCount} node offline dan {$warningCount} node beban tinggi.");

        return Command::SUCCESS;
    }
}
