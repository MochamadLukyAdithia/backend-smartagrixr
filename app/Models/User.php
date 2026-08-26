<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\{Subscription, Payment, AuditLog, Classroom};

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar',
        'email_verified_at',
        'status',
        'failed_login_attempts',
        'locked_until',
        'unej_role',
        'is_unej_verified'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locked_until' => 'datetime',
            'is_unej_verified' => 'boolean'
        ];
    }

    // RELATION
 
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
 
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest('started_at');
    }
 
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
 
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isDosen(): bool
    {
        return $this->unej_role === 'dosen' && $this->is_unej_verified;
    }
    
    public function isMahasiswa(): bool
    {
        return $this->unej_role === 'mahasiswa' && $this->is_unej_verified;
    }
    
    public function isUnejCivitas(): bool
    {
        return $this->is_unej_verified
            && in_array($this->unej_role, ['dosen', 'mahasiswa', 'tendik']);
    }
    
    public function isUmum(): bool
    {
        return $this->unej_role === 'umum' || !$this->is_unej_verified;
    }
 
    /**
     * Cek apakah user punya akses Pro (aktif & tidak expired)
     */
    public function isPro(): bool
    {
        $sub = $this->activeSubscription;
        if (!$sub) return false;
 
        return $sub->plan->slug !== 'free'
            && $sub->isActive();
    }
 
    /**
     * Cek apakah user bisa akses fitur tertentu
     * Contoh: $user->can('upload_3d_asset')
     */
    public function hasFeature(string $feature): bool
    {
        $sub = $this->activeSubscription;
        if (!$sub) return false;
 
        $features = $sub->plan->features ?? [];
        return in_array($feature, $features);
    }
 
    /**
     * Cek apakah masih dalam masa trial
     */
    public function isOnTrial(): bool
    {
        $sub = $this->activeSubscription;
        return $sub?->status === 'trial'
            && $sub->trial_ends_at?->isFuture();
    }
 
    /**
     * Cek apakah Pro berasal dari instansi (gratis)
     */
    public function isProFromInstansi(): bool
    {
        return $this->activeSubscription?->source === 'instansi';
    }
 
    /**
     * Sisa hari subscription
     */
    public function daysRemaining(): ?int
    {
        $sub = $this->activeSubscription;
        if (!$sub || !$sub->expires_at) return null; // null = selamanya
        return max(0, now()->diffInDays($sub->expires_at, false));
    }

    public function teachingClassrooms()
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }
    
    // Kelas yang diikuti (sebagai siswa)
    public function enrolledClassrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_user')
            ->withPivot('role', 'enrolled_at')
            ->withTimestamps();
    }
}
