<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * line_total is not stored: it is (unit_gas_price + unit_cylinder_fee) * quantity.
     * The cylinder size is not stored either: it is product -> cylinder (OrderItem::cylinder()).
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');

            // Customer with an empty cylinder to exchange pays gas only; otherwise gas + cylinder fee.
            $table->boolean('has_own_cylinder')->default(true);

            // Price snapshots (prices change over time, so history keeps what was charged)
            $table->decimal('unit_gas_price', 10, 2);
            $table->decimal('unit_cylinder_fee', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
