<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cylinder is a reusable SIZE (e.g. "11kg") with the price a customer pays when
     * they have no empty cylinder to exchange. Several gas products can share one size,
     * so the price lives here once instead of being repeated on every product (3NF).
     */
    public function up(): void
    {
        Schema::create('cylinders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinders');
    }
};
