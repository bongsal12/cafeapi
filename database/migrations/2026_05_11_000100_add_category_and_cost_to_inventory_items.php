<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'category')) {
                $table->string('category', 80)->default('Other')->after('name');
            }

            if (!Schema::hasColumn('inventory_items', 'cost_per_unit')) {
                $table->decimal('cost_per_unit', 12, 4)->default(0)->after('low_stock_alert');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'cost_per_unit')) {
                $table->dropColumn('cost_per_unit');
            }

            if (Schema::hasColumn('inventory_items', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
