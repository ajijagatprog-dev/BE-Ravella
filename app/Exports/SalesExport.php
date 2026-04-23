<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $dateFrom;

    public function __construct($dateFrom = null)
    {
        $this->dateFrom = $dateFrom;
    }

    public function collection()
    {
        return Product::all()->map(function ($p) {
            $itemsQuery = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.product_id', $p->id);

            if ($this->dateFrom) {
                $itemsQuery->where('orders.created_at', '>=', $this->dateFrom);
            }

            $unitsSold = (clone $itemsQuery)->sum('order_items.quantity');
            $revenue = (clone $itemsQuery)->sum(DB::raw('order_items.price * order_items.quantity'));

            return (object) [
                'product' => $p->name,
                'sku' => $p->sku ?? 'N/A',
                'category' => $p->category ?? 'General',
                'unitsSold' => (int) $unitsSold,
                'revenue' => (float) $revenue,
                'stock' => $p->stock ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Produk',
            'SKU',
            'Kategori',
            'Unit Terjual',
            'Pendapatan (IDR)',
            'Stok Saat Ini',
        ];
    }

    public function map($row): array
    {
        return [
            $row->product,
            $row->sku,
            $row->category,
            $row->unitsSold,
            $row->revenue,
            $row->stock,
        ];
    }
}
