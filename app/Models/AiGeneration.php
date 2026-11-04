<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'ticket_id',
        'prompt',
        'model',
        'task_id',
        'model_url',
        'preview_url',
        'status',
        'refund_reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

