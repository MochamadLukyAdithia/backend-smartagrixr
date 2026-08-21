<?php

namespace App\Services;
 
use App\Jobs\{ProcessExpiredSubscription, SendDunningNotification};
use App\Models\Subscription;
 
class DunningScheduler
{
    /**
     * Dipanggil SEKALI saat subscription dibuat/diperpanjang.
     * Semua job langsung dijadwalkan dengan delay masing-masing.
     *
     * Timeline:
     * ──────────────────────────────────────────────────────
     * Sekarang
     *   │
     *   ├─ dispatch SendDunning(step=1) → delay ke T-30
     *   ├─ dispatch SendDunning(step=2) → delay ke T-7
     *   ├─ dispatch SendDunning(step=3) → delay ke T-3
     *   ├─ dispatch SendDunning(step=4) → delay ke T-1
     *   └─ dispatch ProcessExpired      → delay ke T-0
     *                                          │
     *                                          └─ dispatch ProcessGracePeriodEnded
     *                                                      → delay +7 hari
     * ──────────────────────────────────────────────────────
     */
    public static function schedule(Subscription $subscription): void
    {
        // Instansi tidak punya expiry → tidak perlu dunning
        if (!$subscription->expires_at) {
            return;
        }
 
        $expiresAt = $subscription->expires_at;
 
        // T-30: hanya dispatch jika masih lebih dari 30 hari
        if ($expiresAt->diffInDays(now()) >= 30) {
            SendDunningNotification::dispatch($subscription->id, 1)
                ->delay($expiresAt->copy()->subDays(30));
        }
 
        // T-7
        if ($expiresAt->diffInDays(now()) >= 7) {
            SendDunningNotification::dispatch($subscription->id, 2)
                ->delay($expiresAt->copy()->subDays(7));
        }
 
        // T-3
        if ($expiresAt->diffInDays(now()) >= 3) {
            SendDunningNotification::dispatch($subscription->id, 3)
                ->delay($expiresAt->copy()->subDays(3));
        }
 
        // T-1
        if ($expiresAt->diffInDays(now()) >= 1) {
            SendDunningNotification::dispatch($subscription->id, 4)
                ->delay($expiresAt->copy()->subDay());
        }
 
        // T-0: proses expired (mulai grace period)
        ProcessExpiredSubscription::dispatch($subscription->id)
            ->delay($expiresAt);
    }
}