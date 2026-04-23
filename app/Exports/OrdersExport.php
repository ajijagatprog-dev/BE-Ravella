<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $userId;
    protected $dateFrom;
    protected $status;

    public function __construct($userId = null, $dateFrom = null, $status = null)
    {
        $this->userId = $userId;
        $this->dateFrom = $dateFrom;
        $this->status = $status;
    }

    public function collection()
    {
        $query = Order::with('user');
        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }
        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom);
        }
        if ($this->status && $this->status !== 'all') {
            $query->where('status', strtoupper($this->status));
        }
        return $query->latest()->get();
    }

    public function headings(): array
    {
        $headings = [
            'Order Number',
            'Customer Name',
            'Total Amount',
            'Status',
            'Payment Method',
            'Created At',
        ];
        return $headings;
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->user->name ?? 'Unknown',
            $order->total_amount,
            $order->status,
            $order->payment_method,
            $order->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
