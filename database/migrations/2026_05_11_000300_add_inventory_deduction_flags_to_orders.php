<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'inventory_deducted')) {
                $table->boolean('inventory_deducted')->default(false)->after('payment_ref');
            }

            if (!Schema::hasColumn('orders', 'inventory_deducted_at')) {
                $table->dateTime('inventory_deducted_at')->nullable()->after('inventory_deducted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'inventory_deducted_at')) {
                $table->dropColumn('inventory_deducted_at');
            }

            if (Schema::hasColumn('orders', 'inventory_deducted')) {
                $table->dropColumn('inventory_deducted');
            }
        });
    }
};
