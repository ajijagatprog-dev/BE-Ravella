<?php

namespace App\Exports;

use App\Models\Voucher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VouchersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Voucher::latest()->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Description',
            'Type',
            'Value',
            'Min Purchase',
            'Max Discount',
            'Max Uses',
            'Used Count',
            'Max Per User',
            'Starts At',
            'Expires At',
            'SKU Filter',
            'Is Active',
        ];
    }

    public function map($voucher): array
    {
        return [
            $voucher->id,
            $voucher->code,
            $voucher->description,
            strtoupper($voucher->type),
            $voucher->value,
            $voucher->min_purchase,
            $voucher->max_discount,
            $voucher->max_uses ?? 'Unlimited',
            $voucher->used_count,
            $voucher->max_per_user ?? 'Unlimited',
            $voucher->starts_at,
            $voucher->expires_at,
            $voucher->sku ?? 'All Products',
            $voucher->is_active ? 'Yes' : 'No',
        ];
    }
}
