<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 50)->default('regular');
            $table->string('description', 255)->nullable();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'size']);
        });

        Schema::create('inventory_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_recipe_id')->constrained('inventory_recipes')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity_used', 12, 3);
            $table->string('unit', 30);
            $table->timestamps();

            $table->unique(['inventory_recipe_id', 'inventory_item_id']);
        });

        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->dateTime('count_date');
            $table->string('branch', 120)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_session_id')->constrained('stock_count_sessions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('system_stock', 12, 3)->default(0);
            $table->decimal('actual_count', 12, 3)->default(0);
            $table->decimal('difference', 12, 3)->default(0);
            $table->string('reason', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_count_sessions');
        Schema::dropIfExists('inventory_recipe_items');
        Schema::dropIfExists('inventory_recipes');
    }
};
