<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,      // owner + cashier first: the ledger needs a user for opening stock
            SupplierSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
