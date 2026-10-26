<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas Selesai</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 32px 30px; text-align: left;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">
                                NexSphere
                            </h1>
                            <p style="color: #e0f2fe; margin: 6px 0 0; font-size: 14px;">
                                Notifikasi Proyek & Manajemen Tugas
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 30px;">
                            <div style="display: inline-block; background-color: #dcfce7; color: #15803d; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 9999px; margin-bottom: 16px;">
                                ✓ Tugas Selesai
                            </div>

                            <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.4;">
                                {{ $task->title }}
                            </h2>

                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #475569;">
                                Halo Pengelola Proyek,<br>
                                Tugas di bawah ini telah berhasil diselesaikan
                                @if($actor)
                                    oleh <strong>{{ $actor->name }}</strong> ({{ $actor->email }}).
                                @else
                                    .
                                @endif
                            </p>

                            <!-- Task Details Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table width="100%" cellpadding="4" cellspacing="0" style="font-size: 14px;">
                                            @if($task->list?->space)
                                                <tr>
                                                    <td width="35%" style="color: #64748b; font-weight: 500;">Proyek / Space:</td>
                                                    <td style="color: #0f172a; font-weight: 600;">{{ $task->list->space->name }}</td>
                                                </tr>
                                            @endif
                                            @if($task->list)
                                                <tr>
                                                    <td style="color: #64748b; font-weight: 500;">Daftar Tugas:</td>
                                                    <td style="color: #0f172a;">{{ $task->list->name }}</td>
                                                </tr>
                                            @endif
                                            @if($task->stage)
                                                <tr>
                                                    <td style="color: #64748b; font-weight: 500;">Tahapan Akhir:</td>
                                                    <td style="color: #15803d; font-weight: 600;">{{ $task->stage->name }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td style="color: #64748b; font-weight: 500;">Prioritas:</td>
                                                <td style="color: #0f172a; text-transform: capitalize;">{{ $task->priority }}</td>
                                            </tr>
                                            @if($task->due_at)
                                                <tr>
                                                    <td style="color: #64748b; font-weight: 500;">Tenggat Waktu:</td>
                                                    <td style="color: #0f172a;">{{ \Carbon\Carbon::parse($task->due_at)->translatedFormat('d M Y') }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if($task->description)
                                <div style="margin-bottom: 24px; padding: 14px 18px; background-color: #ffffff; border-left: 3px solid #0284c7; font-size: 14px; color: #334155; line-height: 1.5;">
                                    <strong>Deskripsi Tugas:</strong><br>
                                    {{ Str::limit($task->description, 200) }}
                                </div>
                            @endif

                            @if($task->list?->space)
                                <div style="text-align: center; margin-top: 28px;">
                                    <a href="{{ url('/spaces/' . $task->list->space->slug) }}" style="display: inline-block; background-color: #0284c7; color: #ffffff; font-weight: 600; font-size: 14px; padding: 12px 28px; text-decoration: none; border-radius: 8px;">
                                        Lihat di NexSphere
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                                Email ini dikirim secara otomatis oleh <strong>NexSphere Project Management</strong>.<br>
                                Anda menerima email ini karena terdaftar sebagai Manajer Proyek atau Pemilik Workspace.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
