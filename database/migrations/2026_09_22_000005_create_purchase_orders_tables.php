<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock-in orders placed with a supplier ("purchase orders").
     * Flow: ordered -> received (stock goes in) or cancelled.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();                      // e.g. PO-A1B2C3
            $table->foreignId('supplier_id')->constrained();
            $table->enum('status', ['ordered', 'received', 'cancelled'])->default('ordered');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->nullable();   // null until the delivery is checked in
            $table->decimal('unit_cost', 10, 2);                        // what the supplier charges per unit
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
