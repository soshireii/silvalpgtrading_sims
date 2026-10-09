<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // When / how the order is fulfilled
            $table->date('order_date');
            $table->string('fulfillment_type')->default('delivery');
            $table->date('scheduled_delivery_date')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->string('delivery_address');

            // Pricing totals (order_items holds the per-line detail)
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('cylinder_fee_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            // Payment: cash or GCash only, paid in full — no partial/down payment (company policy).
            // payment_status: not_paid | waiting_for_payment | paid_in_full
            $table->enum('payment_method', ['cash', 'gcash']);
            $table->string('payment_status')->default('not_paid');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();

            // Order lifecycle
            $table->enum('status', ['pending', 'out_for_delivery', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('assigned_delivery_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
