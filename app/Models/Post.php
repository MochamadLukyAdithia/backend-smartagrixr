<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'classroom_id', 'user_id', 'type',
        'title', 'body', 'attachments', 'media',
        'is_pinned', 'published_at',
    ];
 
    protected $casts = [
        'attachments'  => 'array',
        'media'        => 'array',
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
    ];
 
    public function classroom()  { return $this->belongsTo(Classroom::class); }
    public function author()     { return $this->belongsTo(User::class, 'user_id'); }
    public function assignment() { return $this->hasOne(Assignment::class); }
    public function material()   { return $this->hasOne(Material::class); }
 
    public function isPublished(): bool { return $this->published_at !== null; }
    public function isDraft(): bool     { return $this->published_at === null; }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class)
            ->whereNull('parent_id') // hanya top-level comment
            ->with([
                'user:id,name,avatar',
                'replies.user:id,name,avatar',
                'replies.likes',
                'likes',
            ])
            ->latest();
    }
    
    public function likesCount(): int
    {
        return $this->likes()->count();
    }
    
    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}
