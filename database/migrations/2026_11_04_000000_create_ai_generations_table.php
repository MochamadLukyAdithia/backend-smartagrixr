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
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('ticket_id', 64)->unique();
            $table->text('prompt');
            $table->string('model', 50)->default('P2-20260801');
            $table->string('task_id', 100)->nullable();
            $table->text('model_url')->nullable();
            $table->text('preview_url')->nullable();
            $table->enum('status', ['reserved', 'consumed', 'refunded', 'completed', 'failed'])->default('reserved');
            $table->string('refund_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['ip_address', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};

