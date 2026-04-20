<!DOCTYPE html>
<html>
<head>
    <title>Pesanan Berhasil Dibuat</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .header h1 { color: #1a202c; font-size: 24px; margin: 0; }
        .content { margin-bottom: 30px; }
        .order-details { background: #f7fafc; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .order-details p { margin: 5px 0; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #3182ce; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { text-align: center; font-size: 12px; color: #718096; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ravella</h1>
        </div>
        
        <div class="content">
            <p>Halo, <strong>{{ $order->user->name }}</strong>,</p>
            <p>Terima kasih telah berbelanja di Ravella! Pesanan Anda dengan nomor <strong>{{ $order->order_number }}</strong> berhasil dibuat.</p>
            
            <div class="order-details">
                <p><strong>Total Tagihan:</strong> Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
                <p><strong>Metode Pembayaran:</strong> {{ $order->payment_method }}</p>
                <p><strong>Status Pembayaran:</strong> Menunggu Pembayaran</p>
            </div>
            
            <p>Silakan selesaikan pembayaran Anda melalui tautan di bawah ini (jika Anda belum membayarnya). Pesanan Anda akan segera kami proses setelah pembayaran terkonfirmasi.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $order->payment_url }}" class="btn">Lanjutkan Pembayaran</a>
            </div>

            <p>Jika Anda menemui kendala, jangan ragu untuk membalas email ini atau menghubungi customer service kami.</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Ravella. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
