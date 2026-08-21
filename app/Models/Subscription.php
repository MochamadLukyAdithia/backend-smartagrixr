<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'source',
        'started_at', 'expires_at', 'trial_ends_at',
        'cancelled_at', 'cancellation_reason',
        'last_dunning_sent_at', 'dunning_step',
    ];
 
    protected $casts = [
        'started_at'    => 'datetime',
        'expires_at'    => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at'  => 'datetime',
        'last_dunning_sent_at' => 'datetime',
        'dunning_step'         => 'integer',
    ];
 
    public function user()   { return $this->belongsTo(User::class); }
    public function plan()   { return $this->belongsTo(Plan::class); }
    public function payment(){ return $this->hasOne(Payment::class); }
 
    /**
     * Apakah subscription masih aktif?
     */
    public function isActive(): bool
    {
        if (!in_array($this->status, ['active', 'trial'])) return false;
 
        // Tidak ada expiry = selamanya (instansi)
        if (!$this->expires_at) return true;
 
        return $this->expires_at->isFuture();
    }
 
    /**
     * Berapa hari tersisa?
     */
    public function daysRemaining(): ?int
    {
        if (!$this->expires_at) return null;
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}
