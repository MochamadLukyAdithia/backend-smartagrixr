<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;
 
    protected $fillable = ['user_id', 'post_id', 'parent_id', 'body'];
 
    public function user()    { return $this->belongsTo(User::class); }
    public function post()    { return $this->belongsTo(Post::class); }
    public function parent()  { return $this->belongsTo(Comment::class, 'parent_id'); }
 
    // Replies (satu level saja)
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->with('user:id,name,avatar')
            ->latest();
    }
 
    // Like di comment
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }
 
    public function isReply(): bool { return $this->parent_id !== null; }
}   