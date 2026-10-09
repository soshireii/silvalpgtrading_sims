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

        // Default categories so the expense form works right after migrating.
        $now = now();
        DB::table('expense_categories')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            ['Fuel', 'Supplier Restock', 'Salaries', 'Utilities'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
