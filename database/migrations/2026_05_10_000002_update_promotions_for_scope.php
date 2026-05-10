<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('scope_type')->default('product')->after('name');
            $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
        });

        DB::statement('ALTER TABLE promotions ALTER COLUMN product_id DROP NOT NULL');

        Schema::table('promotions', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['scope_type', 'category_id']);
        });

        DB::statement('ALTER TABLE promotions ALTER COLUMN product_id SET NOT NULL');
    }
};
