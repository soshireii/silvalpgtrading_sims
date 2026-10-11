<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/** Sample suppliers so the Stock In flow can be tried straight away — edit them under Suppliers. */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(
            ['name' => 'Davao LPG Depot'],
            [
                'contact_first_name' => 'Maria',
                'contact_last_name' => 'Santos',
                'phone' => '09170000010',
                'address' => 'Davao City',
                'is_active' => true,
            ],
        );

        Supplier::firstOrCreate(
            ['name' => 'Mindanao Gas Trading'],
            [
                'contact_first_name' => 'Juan',
                'contact_last_name' => 'Dela Cruz',
                'phone' => '09170000011',
                'address' => 'Davao City',
                'is_active' => true,
            ],
        );
    }
}
