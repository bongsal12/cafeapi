<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'current_stock')) {
                $table->decimal('current_stock', 12, 3)->default(0)->after('is_active');
            }

            if (!Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 30)->default('unit')->after('current_stock');
            }

            if (!Schema::hasColumn('products', 'low_stock_alert')) {
                $table->decimal('low_stock_alert', 12, 3)->default(0)->after('unit');
            }
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30);
            $table->decimal('before_stock', 12, 3);
            $table->decimal('after_stock', 12, 3);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'low_stock_alert')) {
                $table->dropColumn('low_stock_alert');
            }
            if (Schema::hasColumn('products', 'unit')) {
                $table->dropColumn('unit');
            }
            if (Schema::hasColumn('products', 'current_stock')) {
                $table->dropColumn('current_stock');
            }
        });
    }
};
