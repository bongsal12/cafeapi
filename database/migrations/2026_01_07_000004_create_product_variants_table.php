<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('size');          // regular, medium, large
            $table->decimal('price', 10, 2); // price belongs to product+size

            $table->timestamps();

            // prevent duplicate size per product
            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
