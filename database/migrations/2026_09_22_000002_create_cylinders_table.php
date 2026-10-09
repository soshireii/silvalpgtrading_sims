<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cylinder is a reusable SIZE (e.g. "11kg") with the price a customer pays
     * when they have no empty cylinder to exchange. Several gas products can share
     * one cylinder size (products.cylinder_id). Stock lives on products, not here.
     */
    public function up(): void
    {
        Schema::create('cylinders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();            // e.g. "11kg"
            $table->decimal('price', 10, 2);              // cylinder fee when customer has no empty cylinder
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinders');
    }
};
