<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Imports\VouchersImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherController extends Controller
{
    // ── PUBLIC ─────────────────────────────────────────

    /**
     * GET /api/vouchers/active
     * Retrieve a list of active vouchers for the public display.
     */
    public function active()
    {
        $vouchers = Voucher::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', now());
            })
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $vouchers
        ]);
    }

    /**
     * GET /api/vouchers/validate?code=xxx&subtotal=xxx
     * Validate a voucher code and return the discount amount.
     */
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'     => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kode voucher dan total belanja diperlukan.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->first();

        if (!$voucher) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kode voucher tidak ditemukan.',
            ], 404);
        }

        if (!$voucher->isValid()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Voucher sudah tidak berlaku atau habis masa pakainya.',
            ], 422);
        }

        $subtotal = (float) $request->subtotal;

        if ($subtotal < (float) $voucher->min_purchase) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Minimum pembelian untuk voucher ini adalah Rp ' . number_format($voucher->min_purchase, 0, ',', '.'),
            ], 422);
        }

        $discountAmount = $voucher->calculateDiscount($subtotal);

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher berhasil digunakan!',
            'data'    => [
                'code'            => $voucher->code,
                'description'     => $voucher->description,
                'type'            => $voucher->type,
                'value'           => $voucher->value,
                'discount_amount' => $discountAmount,
                'final_total'     => max(0, $subtotal - $discountAmount),
            ],
        ]);
    }

    // ── ADMIN ─────────────────────────────────────────

    /** GET /api/admin/vouchers */
    public function index()
    {
        $vouchers = Voucher::latest()->get();
        return response()->json(['status' => 'success', 'data' => $vouchers]);
    }

    /** POST /api/admin/vouchers */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'         => 'required|string|max:50|unique:vouchers,code',
            'description'  => 'nullable|string|max:255',
            'type'         => 'required|in:percent,fixed',
            'value'        => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses'     => 'nullable|integer|min:1',
            'is_active'    => 'boolean',
            'starts_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date|after_or_equal:starts_at',
            'sku'          => 'nullable|string|max:100',
            'max_per_user' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper(trim($data['code']));

        $voucher = Voucher::create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher created successfully',
            'data'    => $voucher,
        ], 201);
    }

    /** PUT /api/admin/vouchers/{id} */
    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code'         => 'sometimes|string|max:50|unique:vouchers,code,' . $id,
            'description'  => 'nullable|string|max:255',
            'type'         => 'sometimes|in:percent,fixed',
            'value'        => 'sometimes|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses'     => 'nullable|integer|min:1',
            'is_active'    => 'boolean',
            'starts_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date',
            'sku'          => 'nullable|string|max:100',
            'max_per_user' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $voucher->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher updated successfully',
            'data'    => $voucher,
        ]);
    }

    /** DELETE /api/admin/vouchers/{id} */
    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher deleted successfully',
        ]);
    }

    /**
     * POST /api/admin/vouchers/bulk-import
     * Import vouchers from Excel (.xlsx / .csv)
     * Kolom wajib: kode_voucher, tipe, nilai
     */
    public function bulkImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'File tidak valid. Harap upload file Excel (.xlsx, .xls) atau CSV.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $import = new VouchersImport();
        Excel::import($import, $request->file('file'));

        $failures = $import->failures();
        $failureMessages = [];
        foreach ($failures as $failure) {
            $failureMessages[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Import selesai.',
            'imported' => true,
            'failures' => $failureMessages,
        ]);
    }

    /**
     * GET /api/admin/vouchers/template
     * Download template Excel untuk bulk import voucher (Format Baru)
     */
    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_voucher.xlsx"',
        ];

        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles {
            public function headings(): array {
                return [
                    'Kode Produk / SKU',
                    'Kode Voucher',
                    'Nominal Voucher',
                    'Minimal Purchase',
                    'Qty Voucher',
                    'Maksimal Claim per Buyer',
                    'Periode On',
                    'Periode Off / Kadaluarsa',
                ];
            }
            public function array(): array {
                return [
                    ['SKU-123', 'PROMO77', 50000, 100000, 50, 1, '2026-04-01', '2026-04-30'],
                    ['ALL', 'HEMAT10K', 10000, 50000, 100, 2, '2026-04-01', '2026-12-31'],
                ];
            }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
                return [
                    1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '1F2937']]],
                ];
            }
        }, 'template_voucher.xlsx');
    }
}
