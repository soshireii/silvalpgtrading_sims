<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('cylinder_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');

            // Customer with an empty cylinder to exchange pays gas price only;
            // customer without one pays gas price + the cylinder fee.
            $table->boolean('has_own_cylinder')->default(true);

            // Price snapshots at the time of the order
            $table->decimal('unit_gas_price', 10, 2);
            $table->decimal('unit_cylinder_fee', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);   // (unit_gas_price + unit_cylinder_fee) * quantity
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
