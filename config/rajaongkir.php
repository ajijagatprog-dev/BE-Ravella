<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RajaOngkir (Komerce) API Configuration
    |--------------------------------------------------------------------------
    |
    | API Key untuk setiap jenis layanan RajaOngkir Komerce.
    | Setiap API Key memiliki akses yang berbeda:
    | - shipping_cost  : Digunakan untuk cek ongkos kirim & daftar destinasi
    | - shipping_delivery : Digunakan untuk tracking resi pengiriman
    |
    | Daftarkan API Key di: https://collaborator.komerce.id/api-key
    |
    */

    'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),

    /*
     * API Key untuk cek ongkir (Shipping Cost)
     * Digunakan untuk: cek ongkir, list provinsi, kota, kecamatan
     */
    'api_key' => env('RAJAONGKIR_API_KEY', ''),

    /*
     * API Key untuk tracking pengiriman (Shipping Delivery)
     * Digunakan untuk: tracking resi J&T, JNE, SiCepat, dll
     */
    'delivery_key' => env('RAJAONGKIR_DELIVERY_KEY', ''),
];
