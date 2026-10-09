<?php

namespace Database\Seeders;

use App\Models\Cylinder;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGasProduct('LPG Cylinder 11kg', '11kg', 950.00, 35);
        $this->seedGasProduct('LPG Cylinder 22kg', '22kg', 1850.00, 18);
        $this->seedGasProduct('LPG Cylinder 50kg', '50kg', 4200.00, 8);
    }

    private function seedGasProduct(string $productName, string $cylinderSize, float $gasPrice, int $stock): void
    {
        $cylinder = Cylinder::firstOrCreate(
            ['name' => $cylinderSize],
            ['price' => 1500.00, 'is_active' => true],
        );

        Product::updateOrCreate(
            ['name' => $productName],
            [
                'cylinder_id' => $cylinder->id,
                'gas_price' => $gasPrice,
                'current_stock' => $stock,
                'max_capacity' => 50,
                'reorder_level' => 20,
                'is_active' => true,
            ],
        );
    }
}
