<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stock ledger. Every change to products.current_stock writes one row here.
     *   quantity_change > 0  = STOCK IN   (supplier_delivery, opening_stock, order_cancelled, correction)
     *   quantity_change < 0  = STOCK OUT  (sale, damaged, lost, returned_to_supplier, correction)
     * Which cylinder size a movement belongs to is product -> cylinder, so it is not repeated here.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity_change');
            $table->enum('reason', [
                'opening_stock', 'supplier_delivery', 'sale', 'order_cancelled',
                'damaged', 'lost', 'returned_to_supplier', 'correction',
            ]);
            $table->unsignedInteger('stock_after');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
