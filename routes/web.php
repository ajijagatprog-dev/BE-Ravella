<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route ini bisa diakses Next.js di: http://localhost:8000/api/user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/test', function () {
    return response()->json(['message' => 'API Ravelle Berhasil Terhubung!']);
});
