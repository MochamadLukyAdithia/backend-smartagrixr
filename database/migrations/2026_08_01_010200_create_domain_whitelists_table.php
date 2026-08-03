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
        Schema::create('domain_whitelists', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();              // "unej.ac.id"
            $table->string('instansi_name');                 // "Universitas Jember"
            $table->foreignId('plan_id')->constrained();     // plan yang diberikan otomatis
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_whitelists');
    }
};
