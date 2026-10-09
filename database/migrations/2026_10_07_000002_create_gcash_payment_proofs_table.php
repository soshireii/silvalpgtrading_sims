<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcash_payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('image_path');
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        DB::table('orders')
            ->where('payment_method', 'gcash')
            ->whereIn('status', ['pending', 'out_for_delivery'])
            ->update([
                'payment_status' => 'not_paid',
                'is_paid' => false,
                'paid_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gcash_payment_proofs');
    }
};
