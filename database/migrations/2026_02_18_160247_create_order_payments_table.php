<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('order_payments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('order_id')->constrained()->cascadeOnDelete();

      $table->string('provider', 50)->default('bakong');
      $table->string('status', 20)->default('pending'); // pending|paid|expired|failed

      $table->decimal('amount', 10, 2);
      $table->string('currency', 3)->default('USD');

      $table->text('qr_string')->nullable();     // KHQR payload
      $table->string('bakong_trx_id')->nullable(); // if bakong returns transaction id
      $table->string('merchant_ref', 120)->nullable(); // your reference

      $table->timestamp('expires_at')->nullable();
      $table->json('raw')->nullable(); // store API response
      $table->timestamps();

      $table->index(['order_id', 'status']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('order_payments');
  }
};
