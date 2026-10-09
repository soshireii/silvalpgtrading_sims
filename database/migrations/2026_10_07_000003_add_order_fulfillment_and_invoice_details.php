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
            $table->string('fulfillment_type')->default('delivery')->after('order_date');
            $table->date('scheduled_delivery_date')->nullable()->after('fulfillment_type');
            $table->timestamp('ordered_at')->nullable()->after('scheduled_delivery_date');
            $table->string('invoice_number')->nullable()->unique()->after('order_code');
        });

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'invoice_number' => 'INV-' . substr($order->order_code, 4),
                        'ordered_at' => $order->created_at,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['fulfillment_type', 'scheduled_delivery_date', 'ordered_at', 'invoice_number']);
        });
    }
};
