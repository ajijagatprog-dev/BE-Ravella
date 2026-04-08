<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'is_primary',
        'recipient_name',
        'phone_number',
        'full_address',
        'city',
        'province',
        'postal_code',
        // RajaOngkir IDs — untuk kalkulasi ongkir real-time
        'province_id',
        'city_id',
        'subdistrict_id',
        'subdistrict_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
