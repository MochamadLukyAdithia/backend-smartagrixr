<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class GradeLevel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'level_type',
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
}