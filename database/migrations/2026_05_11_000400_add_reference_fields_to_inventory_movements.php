<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_movements', 'reference_type')) {
                $table->string('reference_type', 50)->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('inventory_movements', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'reference_id')) {
                $table->dropColumn('reference_id');
            }

            if (Schema::hasColumn('inventory_movements', 'reference_type')) {
                $table->dropColumn('reference_type');
            }
        });
    }
};
