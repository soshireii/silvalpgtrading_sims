<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cylinders', function (Blueprint $table) {
            $table->dropColumn(['current_stock', 'max_capacity', 'reorder_level']);
        });
    }

    public function down(): void
    {
        Schema::table('cylinders', function (Blueprint $table) {
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('max_capacity')->default(50);
            $table->unsignedInteger('reorder_level')->default(20);
        });
    }
};
