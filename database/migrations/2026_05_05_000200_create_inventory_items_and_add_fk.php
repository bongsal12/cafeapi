<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable()->index();
                $table->string('image')->nullable();
                $table->decimal('current_stock', 12, 3)->default(0);
                $table->string('unit', 30)->default('unit');
                $table->decimal('low_stock_alert', 12, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Add inventory_item_id to movements and backfill from products where available
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'inventory_item_id')) {
                $table->unsignedBigInteger('inventory_item_id')->nullable()->after('id');
            }
        });

        // Backfill: create one inventory_item per product and point movements to them
        if (Schema::hasTable('products')) {
            $products = \DB::table('products')->select('id','name','slug','current_stock','unit','low_stock_alert','is_active','image')->get();
            foreach ($products as $p) {
                $itemId = \DB::table('inventory_items')->insertGetId([
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'image' => $p->image ?? null,
                    'current_stock' => $p->current_stock ?? 0,
                    'unit' => $p->unit ?? 'unit',
                    'low_stock_alert' => $p->low_stock_alert ?? 0,
                    'is_active' => $p->is_active ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // update movements
                \DB::table('inventory_movements')->where('product_id', $p->id)->update(['inventory_item_id' => $itemId]);
            }
        }
    }

    public function down(): void
    {
        // remove inventory_item_id
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'inventory_item_id')) {
                $table->dropColumn('inventory_item_id');
            }
        });

        Schema::dropIfExists('inventory_items');
    }
};
