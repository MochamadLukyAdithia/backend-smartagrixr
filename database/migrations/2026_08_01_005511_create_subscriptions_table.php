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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', [
                'active',
                'trial',
                'expired',
                'cancelled',
                'pending'
            ])->default('active');
            $table->enum('source', [
                'free',
                'paid',       // bayar via payment gateway
                'instansi',   // email domain instansi
                'trial',      // masa percobaan
                'manual',     // admin beri manual
                'promo'       // kode promo
            ])->default('free');
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();     // null = selamanya (instansi)
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('last_dunning_sent_at')->nullable()->after('cancelled_at');
            $table->unsignedTinyInteger('dunning_step')->default(0)->after('last_dunning_sent_at');
            $table->timestamps();
        
            // Satu user hanya punya 1 subscription aktif
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcriptions');
    }
};
