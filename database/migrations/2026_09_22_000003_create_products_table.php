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
            $table->foreignId('cylinder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                                  // e.g. "LPG Cylinder 11kg"
            $table->decimal('gas_price', 10, 2);                      // gas refill price (cylinder exchange)
            $table->unsignedInteger('current_stock')->default(0);
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
