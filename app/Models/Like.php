<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    protected $fillable = ['user_id', 'likeable_type', 'likeable_id'];
 
    public function user()     { return $this->belongsTo(User::class); }
    public function likeable() { return $this->morphTo(); }
}