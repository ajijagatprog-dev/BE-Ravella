<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Product::all();
    }

    public function headings(): array
    {
        return [
            'Name',
            'SKU',
            'Category',
            'Stock',
            'Price',
            'Sale Price',
            'B2B Price',
            'Discount (%)',
            'Rating',
            'Reviews',
        ];
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->sku ?? 'N/A',
            $product->category ?? 'General',
            $product->stock,
            $product->price,
            $product->sale_price,
            $product->b2b_price,
            $product->discount,
            $product->rating,
            $product->reviews,
        ];
    }
}
