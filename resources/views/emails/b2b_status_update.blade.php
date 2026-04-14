<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pendaftaran B2B</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f4f7; color: #333; }
        .wrapper { max-width: 620px; margin: 30px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { padding: 36px 40px; text-align: center; }
        .header.approved { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
        .header.rejected { background: linear-gradient(135deg, #2d1515 0%, #3d1a1a 100%); }
        .header h1 { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: #c8a96e; font-size: 13px; margin-top: 6px; letter-spacing: 1px; text-transform: uppercase; }
        .status-icon { font-size: 52px; margin: 18px 0 10px; }
        .status-title { font-size: 20px; font-weight: 700; margin-top: 8px; }
        .status-title.approved { color: #c8a96e; }
        .status-title.rejected { color: #e88; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 15px; color: #555; line-height: 1.7; margin-bottom: 20px; }
        .message-box { border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
        .message-box.approved { background: #f0f9f0; border: 1px solid #a3d9a5; }
        .message-box.rejected { background: #fdf0f0; border: 1px solid #e8a5a5; }
        .message-box p { font-size: 14px; line-height: 1.8; }
        .message-box.approved p { color: #2a6b2a; }
        .message-box.rejected p { color: #7a2a2a; }
        .info-card { background: #f9f9fb; border: 1px solid #e8e8f0; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { width: 140px; font-size: 13px; color: #888; flex-shrink: 0; }
        .info-value { font-size: 14px; color: #222; font-weight: 600; }
        .cta { text-align: center; margin: 28px 0; }
        .cta a { display: inline-block; background: #c8a96e; color: #1a1a2e; font-size: 14px; font-weight: 700; padding: 14px 36px; border-radius: 8px; text-decoration: none; }
        .steps { margin-top: 6px; }
        .step { display: flex; align-items: flex-start; margin-bottom: 12px; }
        .step-num { width: 26px; height: 26px; background: #c8a96e; color: #1a1a2e; border-radius: 50%; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 12px; margin-top: 2px; }
        .step-text { font-size: 14px; color: #555; line-height: 1.6; }
        .divider { border: none; border-top: 1px solid #e8e8f0; margin: 24px 0; }
        .contact { font-size: 13px; color: #888; line-height: 1.7; }
        .contact a { color: #c8a96e; text-decoration: none; }
        .footer { background: #f4f4f7; padding: 20px 40px; text-align: center; font-size: 12px; color: #aaa; border-top: 1px solid #e8e8f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header {{ $newStatus }}">
            <h1>Ravella</h1>
            <p>B2B Partner Program</p>
            <div class="status-icon">
                @if($newStatus === 'approved') ✅ @else ❌ @endif
            </div>
            <div class="status-title {{ $newStatus }}">
                @if($newStatus === 'approved')
                    Akun B2B Anda Disetujui!
                @else
                    Pendaftaran B2B Tidak Dapat Diproses
                @endif
            </div>
        </div>

        <div class="body">
            <p class="greeting">
                Halo <strong>{{ $user->name }}</strong>,<br><br>
                Terima kasih telah mendaftarkan diri sebagai mitra B2B Ravella atas nama perusahaan <strong>{{ $user->company_name }}</strong>. Kami telah memproses permohonan Anda.
            </p>

            @if($newStatus === 'approved')
                <div class="message-box approved">
                    <p>
                        🎉 <strong>Selamat!</strong> Tim admin Ravella telah <strong>menyetujui</strong> akun B2B Anda. Mulai sekarang Anda dapat login dan menikmati layanan B2B eksklusif Ravella, termasuk harga khusus mitra dan berbagai keuntungan lainnya.
                    </p>
                </div>

                <div class="info-card">
                    <p style="font-size:13px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:1px; margin-bottom:14px;">📋 Langkah Selanjutnya</p>
                    <div class="steps">
                        <div class="step">
                            <div class="step-num">1</div>
                            <div class="step-text">Login ke portal B2B Ravella menggunakan email dan password yang Anda daftarkan.</div>
                        </div>
                        <div class="step">
                            <div class="step-num">2</div>
                            <div class="step-text">Jelajahi katalog produk eksklusif dengan harga khusus mitra B2B.</div>
                        </div>
                        <div class="step">
                            <div class="step-num">3</div>
                            <div class="step-text">Mulai lakukan pemesanan dan nikmati program loyalitas B2B Ravella.</div>
                        </div>
                    </div>
                </div>

                <div class="cta">
                    <a href="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/auth/login" target="_blank">Login ke Portal B2B →</a>
                </div>

            @else
                <div class="message-box rejected">
                    <p>
                        Kami mohon maaf, setelah melakukan verifikasi, tim admin Ravella <strong>tidak dapat menyetujui</strong> pendaftaran B2B Anda saat ini. Hal ini mungkin disebabkan oleh kelengkapan dokumen atau kriteria kemitraan yang belum terpenuhi.
                    </p>
                </div>

                <div class="info-card">
                    <p style="font-size:13px; color:#555; line-height:1.7;">
                        Jangan berkecil hati. Anda dapat menghubungi tim kami untuk mendapatkan informasi lebih lanjut mengenai alasan penolakan atau cara mengajukan kembali permohonan B2B Anda.
                    </p>
                </div>
            @endif

            <hr class="divider">

            <div class="info-card" style="margin-bottom:0">
                <p style="font-size:13px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">📋 Data Akun Anda</p>
                <div class="info-row">
                    <span class="info-label">Nama</span>
                    <span class="info-value">{{ $user->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $user->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Perusahaan</span>
                    <span class="info-value">{{ $user->company_name }}</span>
                </div>
                <div class="info-row" style="margin-bottom:0">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        @if($newStatus === 'approved')
                            <span style="color:#2a6b2a;font-weight:700;">✅ Disetujui</span>
                        @else
                            <span style="color:#7a2a2a;font-weight:700;">❌ Ditolak</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p class="contact">
                Butuh bantuan? Hubungi tim kami melalui
                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
            </p>
            <p style="margin-top:8px;">Email ini dikirim otomatis oleh sistem Ravella. Jangan balas email ini.</p>
        </div>
    </div>
</body>
</html>
