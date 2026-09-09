<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class LearningContent extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
 
    protected $fillable = [
        'user_id',
        'subject_id',
        'grade_level_id',
        'title',
        'description',
        'thumbnail_path',
        'embed_url',
        'is_published',
    ];
 
    protected $casts = [
        'is_published' => 'boolean',
    ];
 
    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
 
    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }
 
    /**
     * Deteksi apakah embed_url adalah YouTube
     */
    public function getIsYoutubeAttribute(): bool
    {
        return str_contains($this->embed_url ?? '', 'youtube.com')
            || str_contains($this->embed_url ?? '', 'youtu.be');
    }
 
    /**
     * Deteksi apakah embed_url adalah Canva
     */
    public function getIsCanvaAttribute(): bool
    {
        return str_contains($this->embed_url ?? '', 'canva.com');
    }
 
    /**
     * Extract YouTube video ID dari URL
     */
    public function getYoutubeIdAttribute(): ?string
    {
        if (!$this->is_youtube || !$this->embed_url) return null;
 
        preg_match(
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            $this->embed_url,
            $matches
        );
 
        return $matches[1] ?? null;
    }
 
    /**
     * Get embed URL yang siap dipakai di iframe
     * Canva  → langsung pakai embed_url
     * YouTube → convert ke /embed/{video_id}
     */
    public function getEmbedReadyUrlAttribute(): ?string
    {
        if (!$this->embed_url) return null;
 
        if ($this->is_youtube && $this->youtube_id) {
            return "https://www.youtube.com/embed/{$this->youtube_id}?rel=0&showinfo=0";
        }
 
        return $this->embed_url;
    }
 
    /**
     * Deteksi tipe konten dari URL
     */
    public function getContentTypeAttribute(): string
    {
        if ($this->is_youtube) return 'youtube';
        if ($this->is_canva)   return 'canva';
        return 'embed';
    }
}