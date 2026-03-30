<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'expires_at'   => 'nullable|date|after:now',
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
            'expires_at'   => 'nullable|date',
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
}
