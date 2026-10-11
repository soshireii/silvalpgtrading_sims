<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Not stored here on purpose (they can be worked out from other columns, which 3NF forbids):
     *   grand_total    = subtotal + cylinder_fee_total      -> Order::grand_total accessor
     *   is_paid        = payment_status === 'paid_in_full'  -> Order::is_paid accessor
     *   invoice_number = "INV-" + order_code                -> Order::invoice_number accessor
     *   ordered_at     = created_at                         -> Order::ordered_at accessor
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('customer_id')->constrained();

            $table->date('order_date')->index();
            $table->enum('fulfillment_type', ['delivery', 'pickup'])->default('delivery');
            $table->date('scheduled_delivery_date')->nullable();
            $table->string('delivery_address')->nullable();             // null for store pickup

            // Receipt amounts saved at the time of the sale
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('cylinder_fee_total', 10, 2)->default(0);

            // Cash or GCash only, paid in full — no partial/down payment (company policy)
            $table->enum('payment_method', ['cash', 'gcash']);
            $table->enum('payment_status', ['not_paid', 'waiting_for_payment', 'paid_in_full'])->default('not_paid');
            $table->timestamp('paid_at')->nullable();

            $table->enum('status', ['pending', 'out_for_delivery', 'completed', 'cancelled'])->default('pending')->index();
            $table->foreignId('assigned_delivery_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();              // when the order was completed (delivered / picked up)

            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
