<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $table) {
      if (!Schema::hasColumn('orders', 'currency')) {
        $table->string('currency', 3)->default('USD');
      }

      if (!Schema::hasColumn('orders', 'status')) {
        $table->string('status', 20)->default('pending'); // pending|completed|cancelled
      }

      if (!Schema::hasColumn('orders', 'total')) {
        $table->decimal('total', 10, 2)->default(0);
      }

      if (!Schema::hasColumn('orders', 'payment_status')) {
        $table->string('payment_status', 20)->default('unpaid'); // unpaid|pending|paid|expired|failed
      }

      if (!Schema::hasColumn('orders', 'payment_provider')) {
        $table->string('payment_provider', 50)->nullable();
      }

      if (!Schema::hasColumn('orders', 'payment_ref')) {
        $table->string('payment_ref', 120)->nullable();
      }

      if (!Schema::hasColumn('orders', 'paid_at')) {
        $table->timestamp('paid_at')->nullable();
      }
    });
  }

  public function down(): void {
    Schema::table('orders', function (Blueprint $table) {
      // Only drop columns that exist
      $cols = ['currency','status','total','payment_status','payment_provider','payment_ref','paid_at'];
      foreach ($cols as $col) {
        if (Schema::hasColumn('orders', $col)) {
          $table->dropColumn($col);
        }
      }
    });
  }
};
