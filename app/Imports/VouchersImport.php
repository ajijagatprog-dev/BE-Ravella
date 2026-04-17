<?php

namespace App\Imports;

use App\Models\Voucher;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;

class VouchersImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    use SkipsFailures;

    /**
     * Kolom Excel yang diharapkan (sesuai format baru):
     * A: kode_produk_sku
     * B: kode_voucher
     * C: nominal_voucher
     * D: minimal_purchase
     * E: qty_voucher
     * F: maksimal_claim_per_buyer
     * G: periode_on
     * H: periode_off_kadaluarsa
     */
    public function model(array $row)
    {
        // Skip baris dengan kode voucher kosong
        if (empty($row['kode_voucher'])) {
            return null;
        }

        $code = strtoupper(trim($row['kode_voucher']));

        // Jika sudah ada, skip (tidak overwrite)
        if (Voucher::where('code', $code)->exists()) {
            return null;
        }

        $startsAt = null;
        if (!empty($row['periode_on'])) {
            try {
                $startsAt = Carbon::parse($row['periode_on'])->startOfDay();
            } catch (\Exception $e) {
                $startsAt = null;
            }
        }

        $expiresAt = null;
        if (!empty($row['periode_off_kadaluarsa'])) {
            try {
                $expiresAt = Carbon::parse($row['periode_off_kadaluarsa'])->endOfDay();
            } catch (\Exception $e) {
                // Try different formats if needed
                $expiresAt = null;
            }
        }

        $maxUses = null;
        if (!empty($row['qty_voucher']) && is_numeric($row['qty_voucher'])) {
            $maxUses = (int) $row['qty_voucher'];
        }

        $maxPerUser = null;
        if (!empty($row['maksimal_claim_per_buyer']) && is_numeric($row['maksimal_claim_per_buyer'])) {
            $maxPerUser = (int) $row['maksimal_claim_per_buyer'];
        }

        return new Voucher([
            'code'         => $code,
            'sku'          => !empty($row['kode_produk_sku']) ? $row['kode_produk_sku'] : null,
            'description'  => "Voucher Import: " . ($row['kode_produk_sku'] ?? 'General'),
            'type'         => 'fixed', // User format focuses on "Nominal", assuming fixed amount
            'value'        => (float) ($row['nominal_voucher'] ?? 0),
            'min_purchase' => !empty($row['minimal_purchase']) ? (float) $row['minimal_purchase'] : 0,
            'max_discount' => null,
            'max_uses'     => $maxUses,
            'max_per_user' => $maxPerUser,
            'used_count'   => 0,
            'is_active'    => true,
            'starts_at'    => $startsAt,
            'expires_at'   => $expiresAt,
        ]);
    }

    public function rules(): array
    {
        return [
            'kode_voucher' => 'nullable|string',
            'tipe'         => 'nullable|in:percent,fixed',
            'nilai'        => 'nullable|numeric|min:0',
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
