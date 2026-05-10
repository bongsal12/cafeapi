<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('product_id');
            $table->unsignedTinyInteger('percent')->comment('0-100');
            $table->boolean('apply_to_variants')->default(true);
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
