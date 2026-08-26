<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'assignment_id', 'user_id', 'content',
        'attachments', 'score', 'feedback', 'status', 'submitted_at',
    ];
 
    protected $casts = [
        'attachments'  => 'array',
        'submitted_at' => 'datetime',
    ];
 
    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function student()    { return $this->belongsTo(User::class, 'user_id'); }
 
    public function isSubmitted(): bool { return $this->status === 'submitted'; }
    public function isGraded(): bool    { return $this->status === 'graded'; }
}