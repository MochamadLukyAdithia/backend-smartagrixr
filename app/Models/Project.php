<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
 
class Project extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'user_id', 'title', 'description', 'status',
        'thumbnail_path', 'scene_data',
        'editor_session_id', 'last_edited_at',
    ];
 
    protected $casts = [
        'scene_data'     => 'array',
        'last_edited_at' => 'datetime',
    ];
 
    public function user()     { return $this->belongsTo(User::class); }
    public function assets()   { return $this->belongsToMany(Asset::class, 'project_assets'); }
 
    /**
     * Cek apakah scene mengandung aset Pro
     */
    public function hasProAssets(): bool
    {
        $assetIds = collect($this->scene_data['objects'] ?? [])
            ->pluck('asset_id')
            ->filter()
            ->toArray();
 
        if (empty($assetIds)) return false;
 
        return Asset::whereIn('id', $assetIds)
            ->where('is_pro', true)
            ->exists();
    }
 
    /**
     * Sync project_assets dari scene_data
     */
    public function syncAssetsFromScene(): void
    {
        $assetIds = collect($this->scene_data['objects'] ?? [])
            ->pluck('asset_id')
            ->filter()
            ->unique()
            ->toArray();
 
        $this->assets()->sync($assetIds);
    }
 
    /**
     * Cek apakah ada session editor lain yang aktif
     */
    public function hasActiveEditorSession(string $currentSessionId): bool
    {
        return $this->editor_session_id !== null
            && $this->editor_session_id !== $currentSessionId
            && $this->last_edited_at?->gt(now()->subMinutes(5));
    }
}