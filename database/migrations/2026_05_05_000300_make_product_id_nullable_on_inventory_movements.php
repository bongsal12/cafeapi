<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // For Postgres: simply drop NOT NULL constraint on product_id
        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'product_id')) {
            DB::statement('ALTER TABLE inventory_movements ALTER COLUMN product_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // revert by setting NOT NULL if column exists (only safe if no nulls present)
        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'product_id')) {
            DB::statement('ALTER TABLE inventory_movements ALTER COLUMN product_id SET NOT NULL');
        }
    }
};
