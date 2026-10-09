<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cylinders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('max_capacity')->default(50);
            $table->unsignedInteger('reorder_level')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('products')->orderBy('id')->each(function (object $product) use ($now): void {
            DB::table('cylinders')->insert([
                'product_id' => $product->id,
                'price' => $product->cylinder_fee,
                'current_stock' => 0,
                'max_capacity' => $product->max_capacity,
                'reorder_level' => $product->reorder_level,
                'is_active' => $product->is_active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->foreignId('cylinder_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('cylinder_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('inventory_logs')->whereNotNull('cylinder_id')->orderBy('id')->each(function (object $log): void {
            $productId = DB::table('cylinders')->where('id', $log->cylinder_id)->value('product_id');
            DB::table('inventory_logs')->where('id', $log->id)->update(['product_id' => $productId]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cylinder_id');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cylinder_id');
            $table->foreignId('product_id')->nullable(false)->change();
        });

        Schema::dropIfExists('cylinders');
    }
};
