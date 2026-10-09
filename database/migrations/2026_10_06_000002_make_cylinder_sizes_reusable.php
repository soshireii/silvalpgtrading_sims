<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cylinders', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cylinder_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $seenSizes = [];
        DB::table('cylinders')->orderBy('id')->each(function (object $cylinder) use (&$seenSizes): void {
            $product = DB::table('products')->where('id', $cylinder->product_id)->first();
            if (! $product) {
                return;
            }

            preg_match('/\d+(?:\.\d+)?\s*kg/i', $product->name, $matches);
            $size = strtolower(preg_replace('/\s+/', '', $matches[0] ?? $product->name));

            $existingCylinderId = $seenSizes[$size] ?? null;
            if ($existingCylinderId) {
                DB::table('products')->where('id', $product->id)->update(['cylinder_id' => $existingCylinderId]);
                DB::table('order_items')->where('cylinder_id', $cylinder->id)->update(['cylinder_id' => $existingCylinderId]);
                DB::table('inventory_logs')->where('cylinder_id', $cylinder->id)->update(['cylinder_id' => $existingCylinderId]);
                DB::table('cylinders')->where('id', $cylinder->id)->delete();

                return;
            }

            $seenSizes[$size] = $cylinder->id;
            DB::table('cylinders')->where('id', $cylinder->id)->update(['name' => $size]);
            DB::table('products')->where('id', $product->id)->update(['cylinder_id' => $cylinder->id]);
        });

        Schema::table('cylinders', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->unique('name');
            $table->dropUnique(['product_id']);
            $table->dropConstrainedForeignId('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('cylinders', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('products')->whereNotNull('cylinder_id')->orderBy('id')->each(function (object $product): void {
            $cylinder = DB::table('cylinders')->where('id', $product->cylinder_id)->first();
            if ($cylinder) {
                DB::table('cylinders')->where('id', $cylinder->id)->update(['product_id' => $product->id]);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cylinder_id');
        });

        Schema::table('cylinders', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn('name');
            $table->foreignId('product_id')->nullable(false)->change();
            $table->unique('product_id');
        });
    }
};
