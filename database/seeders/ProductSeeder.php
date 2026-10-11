<?php

namespace Database\Seeders;

use App\Models\Cylinder;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds the LPG brands Silva LPG Trading actually sells (from the shop's price board).
 * gas_price = the refill price when the customer exchanges an empty cylinder.
 * The cylinder price (what a customer with NO empty cylinder adds) is set per cylinder SIZE —
 * the board doesn't list it, so these are defaults: change them on the Cylinders page.
 *
 * Opening stock goes through StockService so every product starts with a ledger entry.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'admin')->first();

        $standard = Cylinder::firstOrCreate(['name' => '11kg'], ['price' => 1500.00, 'is_active' => true]);
        $small = Cylinder::firstOrCreate(['name' => 'Small'], ['price' => 500.00, 'is_active' => true]);

        $brands = [
            // name,        cylinder,  gas price, opening stock, max, reorder
            ['Pryce',       $standard, 1390.00, 32, 50, 20],
            ['Gasul',       $standard, 1370.00, 40, 50, 20],
            ['Phoenix',     $standard, 1380.00, 15, 50, 20],
            ['Solane',      $standard, 1480.00, 25, 50, 20],
            ['Fiesta',      $standard, 1370.00, 18, 50, 20],
            ['P-Kalan',     $small,     490.00, 22, 30, 10],
            ['Gasullette',  $small,     470.00, 19, 30, 10],
            ['Budget',      $small,     290.00, 12, 30, 10],
            ['Sol Small',   $small,     290.00, 14, 30, 10],
        ];

        $stock = app(StockService::class);

        foreach ($brands as [$name, $cylinder, $price, $opening, $max, $reorder]) {
            $product = Product::firstOrCreate(
                ['name' => $name],
                [
                    'cylinder_id' => $cylinder->id,
                    'gas_price' => $price,
                    'current_stock' => 0,
                    'max_capacity' => $max,
                    'reorder_level' => $reorder,
                    'is_active' => true,
                ],
            );

            if ($product->wasRecentlyCreated && $opening > 0) {
                $stock->in($product, $opening, 'opening_stock', $owner?->id, 'Opening stock');
            }
        }
    }
}
