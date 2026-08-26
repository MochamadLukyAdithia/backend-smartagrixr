<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'post_id', 'classroom_id', 'due_at', 'max_score', 'allow_late',
    ];
 
    protected $casts = [
        'due_at'     => 'datetime',
        'allow_late' => 'boolean',
    ];
 
    public function post()        { return $this->belongsTo(Post::class); }
    public function classroom()   { return $this->belongsTo(Classroom::class); }
    public function submissions() { return $this->hasMany(Submission::class); }
 
    public function isExpired(): bool
    {
        return $this->due_at && $this->due_at->isPast();
    }
 
    public function submissionByUser(int $userId): ?Submission
    {
        return $this->submissions()->where('user_id', $userId)->first();
    }
}