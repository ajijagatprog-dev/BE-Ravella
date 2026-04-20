<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fonnte WhatsApp API Token
    |--------------------------------------------------------------------------
    |
    | Berfungsi untuk memanggil API WhatsApp Fonnte untuk mengirim notifikasi.
    |
    */
    'token' => env('FONNTE_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin Phone Number
    |--------------------------------------------------------------------------
    |
    | Nomor WhatsApp admin yang akan menerima notifikasi pesanan masuk.
    |
    */
    'admin_phone' => env('ADMIN_PHONE_NUMBER', ''),
];
