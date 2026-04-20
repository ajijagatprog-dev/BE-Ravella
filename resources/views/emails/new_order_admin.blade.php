<!DOCTYPE html>
<html>
<head>
    <title>ALARM: Pesanan Baru Masuk</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 2px solid #ed8936; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; background-color: #fffaf0; padding: 15px; border-radius: 6px; }
        .header h1 { color: #dd6b20; font-size: 24px; margin: 0; }
        .content { margin-bottom: 30px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table th, .details-table td { padding: 10px; border: 1px solid #eee; text-align: left; }
        .details-table th { background-color: #f7fafc; width: 40%; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #dd6b20; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Terdapat Pesanan Baru!</h1>
            <p>Mohon segera di-monitor proses pembayarannya.</p>
        </div>
        
        <div class="content">
            <p>Halo Admin,</p>
            <p>Pelanggan <strong>{{ $order->user->name }}</strong> baru saja membuat pesanan di sistem.</p>
            
            <table class="details-table">
                <tr>
                    <th>Nomor Pesanan</th>
                    <td><strong>{{ $order->order_number }}</strong></td>
                </tr>
                <tr>
                    <th>Nama Pelanggan</th>
                    <td>{{ $order->user->name }} ({{ $order->user->email }})</td>
                </tr>
                <tr>
                    <th>Total Belanja</th>
                    <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Waktu Order</th>
                    <td>{{ $order->created_at->format('d M Y, H:i') }}</td>
                </tr>
            </table>
            
            <p>Pesanan masih berstatus <strong>PENDING</strong> menunggu konfirmasi pembayaran Gateway (Xendit). Anda dapat memantau statusnya melalui Dashboard Admin.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/admin/orders" class="btn">Buka Halaman Admin</a>
            </div>
        </div>
    </div>
</body>
</html>
