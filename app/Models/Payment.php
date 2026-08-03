<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'subscription_id', 'order_id',
        'external_id', 'amount', 'status', 'payment_method',
        'gateway_response', 'snap_token', 'paid_at', 'expired_at'
    ];
 
    protected $casts = [
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'expired_at'       => 'datetime',
        'amount'           => 'integer',
    ];
 
    public function user()         { return $this->belongsTo(User::class); }
    public function plan()         { return $this->belongsTo(Plan::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
 
    public function isSuccess(): bool { return $this->status === 'success'; }
    public function isPending(): bool { return $this->status === 'pending'; }
}