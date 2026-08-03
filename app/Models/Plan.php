<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price',
        'billing_cycle', 'max_assets', 'max_storage_mb',
        'max_classes', 'features', 'is_active', 'sort_order'
    ];
 
    protected $casts = [
        'features'  => 'array',
        'is_active' => 'boolean',
        'price'     => 'integer',
    ];
 
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
 
    public function isFree(): bool
    {
        return $this->slug === 'free';
    }
 
    /**
     * Format harga ke rupiah
     */
    public function formattedPrice(): string
    {
        if ($this->price === 0) return 'Gratis';
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
