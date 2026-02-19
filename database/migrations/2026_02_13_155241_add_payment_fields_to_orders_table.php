<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->index(); // bakong
            $table->string('payment_status')->default('unpaid')->index(); // unpaid|pending|paid|failed|expired
            $table->string('currency', 3)->default('USD'); // USD only

            $table->text('khqr_string')->nullable();
            $table->string('khqr_md5', 32)->nullable()->index();

            $table->string('bakong_full_hash')->nullable(); // optional store returned full hash
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'currency',
                'khqr_string',
                'khqr_md5',
                'bakong_full_hash',
                'payment_expires_at',
                'paid_at',
            ]);
        });
    }
};
