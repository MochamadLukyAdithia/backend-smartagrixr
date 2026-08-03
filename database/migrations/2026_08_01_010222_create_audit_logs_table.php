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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // siapa yang kena
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // siapa yang melakukan (null = sistem)
            $table->string('event');                         // "subscription.created"
            $table->string('entity_type');                   // "Subscription"
            $table->unsignedBigInteger('entity_id');         // ID entity yang berubah
            $table->json('old_values')->nullable();          // state sebelum
            $table->json('new_values')->nullable();          // state sesudah
            $table->text('reason')->nullable();              // kenapa perubahan terjadi
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();            // data tambahan bebas
            $table->timestamps();
        
            $table->index(['user_id', 'event']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
