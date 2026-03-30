<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return User::whereIn('role', ['customer', 'b2b'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Role',
            'Phone Number',
            'Company Name',
            'NPWP',
            'B2B Status',
            'Joined At',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            strtoupper($user->role),
            $user->phone_number,
            $user->company_name ?? '-',
            $user->npwp ?? '-',
            $user->b2b_status ?? '-',
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
