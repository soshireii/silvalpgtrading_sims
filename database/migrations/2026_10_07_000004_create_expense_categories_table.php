<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        $names = ['Fuel', 'Supplier Restock', 'Salaries', 'Utilities'];
        $names = array_merge($names, DB::table('expenses')->distinct()->pluck('category')->all());

        $categories = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $categories[mb_strtolower($name)] = $name;
            }
        }

        $now = now();
        DB::table('expense_categories')->insertOrIgnore(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            array_values($categories),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};