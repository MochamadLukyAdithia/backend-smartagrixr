<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Subject extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];
 
    protected $casts = [
        'is_active' => 'boolean',
    ];
 
    public function contents()
    {
        return $this->hasMany(LearningContent::class);
    }
 
    public function publishedContents()
    {
        return $this->hasMany(LearningContent::class)
            ->where('is_published', true);
    }
}