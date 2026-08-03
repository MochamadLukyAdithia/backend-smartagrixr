<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained();
            $table->string('order_id')->unique();            // "SXAR-20240101-001"
            $table->string('external_id')->nullable();       // ID dari Midtrans
            $table->unsignedInteger('amount');               // dalam rupiah
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'expired',
                'refunded',
                'challenge'  // Midtrans: perlu review manual
            ])->default('pending');
            $table->string('payment_method')->nullable();    // "gopay", "bca_va", "qris"
            $table->json('gateway_response')->nullable();    // raw response dari Midtrans
            $table->string('snap_token')->nullable();        // Midtrans Snap token
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        
            $table->index(['order_id']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
