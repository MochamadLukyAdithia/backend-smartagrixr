<?php

namespace App\Jobs;
 
use App\Models\{AuditLog, Plan, Subscription};
use App\Notifications\GracePeriodStartedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\DB;
 
class ProcessExpiredSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 
    public int $tries   = 3;  // retry 3x jika gagal
    public int $timeout = 60; // timeout 60 detik
 
    public function __construct(
        public readonly int $subscriptionId
    ) {}
 
    public function handle(): void
    {
        $subscription = Subscription::with(['user', 'plan'])
            ->find($this->subscriptionId);

        if (!$subscription || $subscription->status !== 'active') {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $user    = $subscription->user;
            $oldPlan = $subscription->plan->slug;

            // Langsung expired
            $subscription->update(['status' => 'expired']);

            // Buat subscription Free
            $freePlan = Plan::where('slug', 'free')->firstOrFail();
            Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $freePlan->id,
                'status'     => 'active',
                'source'     => 'free',
                'started_at' => now(),
                'expires_at' => null,
            ]);

            // Clear Pennant cache
            \Laravel\Pennant\Feature::for($user)->flushCache();

            // Audit log
            AuditLog::record(
                event:     'subscription.expired',
                entity:    $subscription,
                oldValues: ['status' => 'active', 'plan' => $oldPlan],
                newValues: ['status' => 'expired', 'downgraded_to' => 'free'],
                reason:    'Subscription melewati expires_at',
            );

            // Notifikasi
            $user->notify(new SubscriptionExpiredNotification());
        });
    }
 
    // Dipanggil setelah semua retry habis
    public function failed(\Throwable $e): void
    {
        \Log::error(
            "ProcessExpiredSubscription gagal " .
            "untuk subscription #{$this->subscriptionId}: " .
            $e->getMessage()
        );
    }
}
