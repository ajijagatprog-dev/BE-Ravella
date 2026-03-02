<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'admin_role' => 'super_admin',
        ]);

        // 2. Admin Biasa
        User::create([
            'name' => 'Admin Content',
            'email' => 'admin2@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'admin_role' => 'admin_biasa',
        ]);

        // 3. Dummy Customer
        User::create([
            'name' => 'Customer Satu',
            'email' => 'customer@example.com',
            'password' => Hash::make('customer123'),
            'role' => 'customer',
            'phone_number' => '081234567890',
            'address' => 'Jl. Customer 123, Jakarta',
            'loyalty_points' => 1500,
        ]);

        // 4. Dummy B2B Approved
        User::create([
            'name' => 'Mitra B2B Aktif',
            'email' => 'b2b@example.com',
            'password' => Hash::make('b2b123'),
            'role' => 'b2b',
            'company_name' => 'PT. Global B2B',
            'npwp' => '12.345.678.9-000.000',
            'b2b_status' => 'approved',
            'phone_number' => '081987654321',
        ]);

        // 5. Dummy B2B Pending
        User::create([
            'name' => 'Mitra B2B Baru',
            'email' => 'b2b_pending@example.com',
            'password' => Hash::make('b2b123'),
            'role' => 'b2b',
            'company_name' => 'CV. Mitra Setia',
            'npwp' => '98.765.432.1-111.000',
            'b2b_status' => 'pending',
            'phone_number' => '082233445566',
        ]);
    }
}
