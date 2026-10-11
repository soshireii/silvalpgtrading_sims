<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cylinder_id')->constrained();           // size + deposit price (restrict on delete)
            $table->string('name');                                    // e.g. "Solane"
            $table->decimal('gas_price', 10, 2);                        // refill price when exchanging a cylinder
            $table->unsignedInteger('current_stock')->default(0);       // running balance; ONLY StockService changes it
            $table->unsignedInteger('max_capacity')->default(50);
            $table->unsignedInteger('reorder_level')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
