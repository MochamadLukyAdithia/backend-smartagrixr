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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // "Free", "Pro", "Enterprise"
            $table->string('slug')->unique();                // "free", "pro", "enterprise"
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0);    // dalam rupiah
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime', 'none'])->default('none');
            $table->unsignedInteger('max_assets')->default(3);
            $table->unsignedInteger('max_storage_mb')->default(512);
            $table->unsignedInteger('max_classes')->default(1);
            $table->json('features')->nullable();            // ["upload_3d", "generate_qr", ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
