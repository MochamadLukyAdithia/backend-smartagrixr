<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'user_id', 'name', 'file_path', 'thumbnail_path',
        'type', 'category_id', 'is_pro', 'is_public',
        'file_size', 'original_name',
    ];
 
    protected $casts = [
        'is_pro'    => 'boolean',
        'is_public' => 'boolean',
        'file_size' => 'integer',
    ];
 
    public function category()  { return $this->belongsTo(AssetCategory::class, 'category_id'); }
    public function user()     { return $this->belongsTo(User::class); }
    public function projects() { return $this->belongsToMany(Project::class, 'project_assets'); }
}