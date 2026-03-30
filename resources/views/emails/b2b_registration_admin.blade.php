<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran B2B Baru</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f4f7; color: #333; }
        .wrapper { max-width: 620px; margin: 30px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 36px 40px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: #c8a96e; font-size: 13px; margin-top: 6px; letter-spacing: 1px; text-transform: uppercase; }
        .badge { display: inline-block; background: #c8a96e; color: #1a1a2e; font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 20px; margin-top: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .body { padding: 36px 40px; }
        .intro { font-size: 15px; color: #555; line-height: 1.7; margin-bottom: 24px; }
        .intro strong { color: #1a1a2e; }
        .info-card { background: #f9f9fb; border: 1px solid #e8e8f0; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .info-card h3 { font-size: 13px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .info-row { display: flex; margin-bottom: 12px; }
        .info-label { width: 140px; font-size: 13px; color: #888; flex-shrink: 0; }
        .info-value { font-size: 14px; color: #222; font-weight: 600; }
        .status-badge { display: inline-block; background: #fff3cd; color: #856404; border: 1px solid #ffc107; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
        .cta { text-align: center; margin: 28px 0; }
        .cta a { display: inline-block; background: #c8a96e; color: #1a1a2e; font-size: 14px; font-weight: 700; padding: 14px 36px; border-radius: 8px; text-decoration: none; letter-spacing: 0.5px; }
        .cta a:hover { background: #b8954e; }
        .note { background: #fffbf0; border-left: 4px solid #c8a96e; padding: 14px 18px; border-radius: 0 6px 6px 0; font-size: 13px; color: #666; line-height: 1.6; }
        .footer { background: #f4f4f7; padding: 20px 40px; text-align: center; font-size: 12px; color: #aaa; border-top: 1px solid #e8e8f0; }
        .footer a { color: #c8a96e; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Ravella</h1>
            <p>B2B Partner Program</p>
            <div class="badge">⚡ Pendaftaran Baru Masuk</div>
        </div>

        <div class="body">
            <p class="intro">
                Halo Admin,<br><br>
                Ada <strong>pendaftaran B2B baru</strong> yang membutuhkan review dan persetujuan Anda. Berikut detail pendaftar:
            </p>

            <div class="info-card">
                <h3>📋 Detail Pendaftar</h3>
                <div class="info-row">
                    <span class="info-label">Nama</span>
                    <span class="info-value">{{ $applicant->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $applicant->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">No. HP</span>
                    <span class="info-value">{{ $applicant->phone_number ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Perusahaan</span>
                    <span class="info-value">{{ $applicant->company_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">NPWP</span>
                    <span class="info-value">{{ $applicant->npwp ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Alamat</span>
                    <span class="info-value">{{ $applicant->address ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Waktu Daftar</span>
                    <span class="info-value">{{ $applicant->created_at->setTimezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value"><span class="status-badge">⏳ Menunggu Review</span></span>
                </div>
            </div>

            <div class="cta">
                <a href="{{ config('app.url') }}/admin/users?role=b2b&status=pending" target="_blank">
                    Buka Halaman Admin → Review Sekarang
                </a>
            </div>

            <div class="note">
                💡 Login ke dashboard admin, buka menu <strong>User Management</strong>, filter tab <strong>B2B Partner</strong>, lalu temukan akun atas nama <strong>{{ $applicant->name }}</strong> untuk menyetujui atau menolak pendaftaran ini.
            </div>
        </div>

        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem <a href="{{ config('app.url') }}">Ravella</a>.</p>
            <p style="margin-top:6px;">Jangan balas email ini.</p>
        </div>
    </div>
</body>
</html>
