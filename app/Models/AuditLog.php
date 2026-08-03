<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'actor_id', 'event', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'reason', 'ip_address', 'user_agent', 'metadata'
    ];
 
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
    ];
 
    public function user()  { return $this->belongsTo(User::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
 
    /**
     * Helper utama untuk mencatat log
     */
    public static function record(
        string $event,
        Model  $entity,
        array  $oldValues = [],
        array  $newValues = [],
        string $reason    = '',
        ?int   $actorId   = null,
        array  $metadata  = []
    ): self {
        return self::create([
            'user_id'     => $entity instanceof User ? $entity->id : ($entity->user_id ?? null),
            'actor_id'    => $actorId, // null = sistem otomatis
            'event'       => $event,
            'entity_type' => class_basename($entity),
            'entity_id'   => $entity->getKey(),
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'reason'      => $reason,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'metadata'    => $metadata ?: null,
        ]);
    }
}
