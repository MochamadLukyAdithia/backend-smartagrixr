<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'post_id', 'classroom_id', 'category',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
