<?php

namespace App\Jobs;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDunningNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 
    public int $tries = 3;
 
    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $step, // 1=T-30, 2=T-7, 3=T-3, 4=T-1
    ) {}
 
    public function handle(): void
    {
        $subscription = Subscription::with(['user', 'plan'])
            ->find($this->subscriptionId);
 
        // Guard 1: sudah perpanjang atau expired → skip
        if (!$subscription || $subscription->status !== 'active') {
            return;
        }
 
        // Guard 2: expires_at sudah lewat → skip
        if ($subscription->expires_at?->isPast()) {
            return;
        }
 
        $user     = $subscription->user;
        $daysLeft = (int) now()->diffInDays($subscription->expires_at, false);
 
        // Guard 3: toleransi ±1 hari (jika queue delay tidak presisi)
        $expectedDays = [1 => 30, 2 => 7, 3 => 3, 4 => 1][$this->step] ?? 0;
        if (abs($daysLeft - $expectedDays) > 1) {
            return;
        }
 
        // Kirim notifikasi
        $user->notify(new \App\Notifications\DunningNotification(
            step:     $this->step,
            daysLeft: $daysLeft,
            plan:     $subscription->plan->slug,
            renewUrl: '/pricing',
        ));
 
        // Catat step dunning
        $subscription->update([
            'dunning_step'         => $this->step,
            'last_dunning_sent_at' => now(),
        ]);
    }
}