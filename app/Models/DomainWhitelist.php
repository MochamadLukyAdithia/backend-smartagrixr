<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainWhitelist extends Model
{
    protected $fillable = ['domain', 'instansi_name', 'plan_id', 'is_active', 'notes'];
 
    protected $casts = ['is_active' => 'boolean'];
 
    public function plan() { return $this->belongsTo(Plan::class); }
 
    /**
     * Ekstrak domain dari email
     * "guru@unej.ac.id" → "unej.ac.id"
     */
    public static function extractDomain(string $email): string
    {
        return strtolower(substr(strrchr($email, '@'), 1));
    }
 
    /**
     * Cek apakah domain ada di whitelist
     */
    public static function isWhitelisted(string $domain): bool
    {
        return self::where('domain', $domain)
            ->where('is_active', true)
            ->exists();
    }
 
    /**
     * Ambil data whitelist beserta plan
     */
    public static function findByDomain(string $domain): ?self
    {
        return self::with('plan')
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();
    }
}
