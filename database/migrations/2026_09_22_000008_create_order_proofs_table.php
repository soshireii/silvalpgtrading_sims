<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo proofs attached to an order — merges the old delivery_proofs and
     * gcash_payment_proofs tables, which had the same shape.
     *   kind = payment  -> GCash receipt uploaded by owner/cashier
     *   kind = delivery -> delivery photo uploaded by delivery staff, then reviewed
     * An order has at most one proof of each kind.
     */
    public function up(): void
    {
        Schema::create('order_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['payment', 'delivery']);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('image_path');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();        // note from the uploader
            $table->text('review_note')->nullable();    // note from the reviewer
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_proofs');
    }
};
