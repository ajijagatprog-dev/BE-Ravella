<?php

namespace App\Imports;

use App\Models\ProductPromotion;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;

class ProductPromotionImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    use SkipsFailures;

    protected $type; // 'discount' or 'flash_sale'

    public function __construct($type = 'discount')
    {
        $this->type = $type;
    }

    /**
     * Kolom Excel yang diharapkan:
     * A: kode_produk_sku
     * B: nama_promo
     * C: tipe_potongan (percent/fixed)
     * D: nilai_potongan
     * E: periode_on (Wajib untuk Flash Sale)
     * F: periode_off (Wajib untuk Flash Sale)
     */
    public function model(array $row)
    {
        if (empty($row['kode_produk_sku']) || empty($row['nilai_potongan'])) {
            return null;
        }

        $sku = trim($row['kode_produk_sku']);

        // Hapus promo lama untuk SKU yang sama agar tidak bentrok
        ProductPromotion::where('sku', $sku)->where('type', $this->type)->delete();

        $startsAt = null;
        if (!empty($row['periode_on'])) {
            try {
                $startsAt = Carbon::parse($row['periode_on'])->startOfDay();
            } catch (\Exception $e) {
                $startsAt = null;
            }
        }

        $endsAt = null;
        if (!empty($row['periode_off'])) {
            try {
                $endsAt = Carbon::parse($row['periode_off'])->endOfDay();
            } catch (\Exception $e) {
                $endsAt = null;
            }
        }

        return new ProductPromotion([
            'sku'            => $sku,
            'name'           => $row['nama_promo'] ?? ($this->type === 'flash_sale' ? 'Flash Sale' : 'Product Discount'),
            'type'           => $this->type,
            'discount_type'  => ($row['tipe_potongan'] === 'percent' || $row['tipe_potongan'] === 'fixed') ? $row['tipe_potongan'] : 'fixed',
            'discount_value' => (float) $row['nilai_potongan'],
            'starts_at'      => $startsAt,
            'ends_at'        => $endsAt,
            'is_active'      => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'kode_produk_sku' => 'required|string',
            'nilai_potongan'  => 'required|numeric|min:0',
            'tipe_potongan'   => 'nullable|in:percent,fixed',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
