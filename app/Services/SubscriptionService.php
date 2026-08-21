<?php

namespace App\Services;

use App\Models\{AuditLog, DomainWhitelist, Plan, Payment, Subscription, User};
use App\Services\DunningScheduler;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Dipanggil saat user baru sign up.
     * Otomatis cek domain → assign Free atau Pro (instansi)
     */
    public function assignOnRegister(User $user): Subscription
    {
        $domain    = DomainWhitelist::extractDomain($user->email);
        $whitelist = DomainWhitelist::findByDomain($domain);
 
        if ($whitelist) {
            return $this->createInstansiSubscription($user, $whitelist);
        }
 
        return $this->createFreeSubscription($user);
    }
 
    /**
     * Buat subscription FREE
     */
    public function createFreeSubscription(User $user): Subscription
    {
        $plan = Plan::where('slug', 'free')->firstOrFail();
 
        $subscription = Subscription::create([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'status'     => 'active',
            'source'     => 'free',
            'started_at' => now(),
            'expires_at' => null,
        ]);
 
        AuditLog::record(
            event:     'subscription.created',
            entity:    $subscription,
            oldValues: [],
            newValues: ['plan' => 'free', 'source' => 'free'],
            reason:    'User baru daftar dengan email umum',
        );
 
        return $subscription;
    }
 
    /**
     * Buat subscription PRO otomatis karena domain instansi
     */
    public function createInstansiSubscription(User $user, $whitelist): Subscription
    {
        // Nonaktifkan subscription lama jika ada
        $this->deactivateExisting($user);
 
        $subscription = Subscription::create([
            'user_id'    => $user->id,
            'plan_id'    => $whitelist->plan_id,
            'status'     => 'active',
            'source'     => 'instansi',
            'started_at' => now(),
            'expires_at' => null, // selamanya selama email aktif
        ]);
 
        AuditLog::record(
            event:     'subscription.auto_upgraded',
            entity:    $subscription,
            oldValues: ['plan' => 'free'],
            newValues: [
                'plan'     => $whitelist->plan->slug,
                'source'   => 'instansi',
                'instansi' => $whitelist->instansi_name,
                'domain'   => $whitelist->domain,
            ],
            reason: "Domain {$whitelist->domain} terdaftar sebagai instansi: {$whitelist->instansi_name}",
            metadata: ['domain' => $whitelist->domain, 'instansi_id' => $whitelist->id]
        );

        DunningScheduler::schedule($subscription);
 
        return $subscription;
    }
 
    /**
     * Upgrade ke Pro setelah payment berhasil
     */
    public function upgradeAfterPayment(User $user, Plan $plan, Payment $payment): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $payment) {

            $payment->refresh();
            if ($payment->subscription_id) {
                return Subscription::findOrFail($payment->subscription_id);
            }

            $previousActive = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($previousActive) {
                $previousActive->update([
                    'status'              => 'cancelled',
                    'cancelled_at'        => now(),
                    'cancellation_reason' => 'Digantikan subscription baru setelah upgrade pembayaran',
                ]);
            }

            $startedAt = now();
            $expiresAt = match ($plan->billing_cycle) {
                'monthly' => $startedAt->copy()->addMonth(),
                'yearly'  => $startedAt->copy()->addYear(),
                default   => $startedAt->copy()->addMonth(),
            };

            $subscription = Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'status'     => 'active',
                'source'     => 'paid',
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
            ]);

            $payment->update(['subscription_id' => $subscription->id]);

            AuditLog::record(
                event:     'subscription.activated',
                entity:    $subscription,
                oldValues: [],
                newValues: [
                    'plan'       => $plan->slug,
                    'started_at' => $startedAt->toDateTimeString(),
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'payment_id' => $payment->id,
                ],
                reason: "Upgrade otomatis setelah pembayaran sukses untuk plan {$plan->name}",
            );

            DunningScheduler::schedule($subscription);

            return $subscription;
        });
    }
 
    /**
     * Nonaktifkan semua subscription aktif user
     */
    private function deactivateExisting(User $user): void
    {
        $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'expired']);
    }   
}