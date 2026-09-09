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
        Schema::create('learning_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->string('title');
            $table->text('description');
            $table->string('thumbnail_path')->nullable();
            $table->text('embed_url');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        
            $table->index(['subject_id', 'grade_level_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_contents');
    }
};
