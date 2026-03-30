<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $userId;

    public function __construct($userId = null)
    {
        $this->userId = $userId;
    }

    public function collection()
    {
        $query = Order::with('user');
        if ($this->userId) {
            $query->where('user_id', $this->userId);
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
