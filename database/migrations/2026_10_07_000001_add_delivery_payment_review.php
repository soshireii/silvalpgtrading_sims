<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('not_paid')->after('payment_method');
        });

        DB::table('orders')
            ->where('is_paid', true)
            ->update(['payment_status' => 'paid_in_full']);

        DB::table('orders')
            ->whereNotNull('assigned_delivery_id')
            ->whereIn('status', ['pending', 'out_for_delivery'])
            ->update([
                'payment_status' => 'waiting_for_payment',
                'is_paid' => false,
                'paid_at' => null,
            ]);

        Schema::table('delivery_proofs', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('proof_image_path');
            $table->foreignId('reviewed_by')->nullable()->after('delivery_staff_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('completed_at');
            $table->text('review_note')->nullable()->after('remarks');
            $table->timestamp('completed_at')->nullable()->change();
        });

        $completedProofIds = DB::table('delivery_proofs')
            ->join('orders', 'delivery_proofs.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->pluck('delivery_proofs.id');

        DB::table('delivery_proofs')
            ->whereIn('id', $completedProofIds)
            ->update([
                'status' => 'approved',
                'reviewed_at' => DB::raw('completed_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('delivery_proofs', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'reviewed_by', 'reviewed_at', 'review_note']);
            $table->timestamp('completed_at')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
