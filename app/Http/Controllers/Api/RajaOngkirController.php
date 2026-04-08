<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RajaOngkirController extends Controller
{
    private string $baseUrl;
    private string $apiKey;
    private string $deliveryKey;

    public function __construct()
    {
        $this->baseUrl    = config('rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');
        $this->apiKey     = config('rajaongkir.api_key', '');
        $this->deliveryKey = config('rajaongkir.delivery_key', '');
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: header untuk Shipping Cost API
    // ─────────────────────────────────────────────────────────────
    private function costHeaders(): array
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'key'          => $this->apiKey,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: header untuk Shipping Delivery API (tracking)
    // ─────────────────────────────────────────────────────────────
    private function deliveryHeaders(): array
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'key'          => $this->deliveryKey,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/rajaongkir/provinces
    // Mengambil daftar semua provinsi
    // ─────────────────────────────────────────────────────────────
    public function getProvinces()
    {
        $response = Http::withHeaders($this->costHeaders())
            ->get("{$this->baseUrl}/destination/province");

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data provinsi dari RajaOngkir.',
                'error'   => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()['data'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/rajaongkir/cities?province_id=xxx
    // Mengambil daftar kota berdasarkan province_id
    // ─────────────────────────────────────────────────────────────
    public function getCities(Request $request)
    {
        $provinceId = $request->query('province_id');

        $response = Http::withHeaders($this->costHeaders())
            ->get("{$this->baseUrl}/destination/city", [
                'province_id' => $provinceId,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kota dari RajaOngkir.',
                'error'   => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()['data'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/rajaongkir/subdistricts?city_id=xxx
    // Mengambil daftar kecamatan berdasarkan city_id
    // ─────────────────────────────────────────────────────────────
    public function getSubdistricts(Request $request)
    {
        $cityId = $request->query('city_id');

        $response = Http::withHeaders($this->costHeaders())
            ->get("{$this->baseUrl}/destination/subdistrict", [
                'city_id' => $cityId,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kecamatan dari RajaOngkir.',
                'error'   => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()['data'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/rajaongkir/cost
    // Menghitung ongkos kirim
    //
    // Body (JSON):
    // {
    //   "origin"       : "kota/subdistrict id asal",
    //   "destination"  : "kota/subdistrict id tujuan",
    //   "weight"       : 1000,        (dalam gram)
    //   "courier"      : "jnt",       (jnt, jne, sicepat, dll)
    // }
    // ─────────────────────────────────────────────────────────────
    public function checkCost(Request $request)
    {
        $request->validate([
            'origin'      => 'required',
            'destination' => 'required',
            'weight'      => 'required|numeric|min:1',
            'courier'     => 'required|string',
        ]);

        $response = Http::withHeaders($this->costHeaders())
            ->post("{$this->baseUrl}/cost", [
                'origin'      => $request->origin,
                'destination' => $request->destination,
                'weight'      => $request->weight,
                'courier'     => $request->courier,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghitung ongkos kirim.',
                'error'   => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()['data'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/rajaongkir/track?waybill=xxx&courier=jnt
    // Tracking resi pengiriman — membutuhkan Shipping Delivery API Key
    // ─────────────────────────────────────────────────────────────
    public function trackShipment(Request $request)
    {
        $request->validate([
            'waybill' => 'required|string',
            'courier' => 'required|string',
        ]);

        if (empty($this->deliveryKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping Delivery API Key belum dikonfigurasi. Tambahkan RAJAONGKIR_DELIVERY_KEY di file .env.',
            ], 503);
        }

        $response = Http::withHeaders($this->deliveryHeaders())
            ->post("{$this->baseUrl}/track", [
                'waybill' => $request->waybill,
                'courier' => $request->courier,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tracking.',
                'error'   => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()['data'] ?? [],
        ]);
    }
}
