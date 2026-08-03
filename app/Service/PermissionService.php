
<?php

class PermissionService
{
    /**
     * Cek apakah user bisa akses fitur tertentu
     * Ini adalah inti dari "Permission Check" di sequence diagram
     */
    public function check(User $user, string $feature): array
    {
        $subscription = $user->activeSubscription()->with('plan')->first();
 
        // Tidak punya subscription sama sekali
        if (!$subscription) {
            return $this->denied($feature, 'Tidak ada subscription aktif');
        }
 
        // Subscription expired
        if (!$subscription->isActive()) {
            return $this->denied($feature, 'Subscription sudah expired', [
                'expired_at'  => $subscription->expires_at,
                'upgrade_url' => '/pricing',
            ]);
        }
 
        // Cek fitur di plan
        $features = $subscription->plan->features ?? [];
        if (!in_array($feature, $features)) {
            return $this->denied($feature, 'Fitur tidak tersedia di plan kamu', [
                'current_plan' => $subscription->plan->slug,
                'upgrade_url'  => '/pricing',
                'plans_with_feature' => $this->plansWithFeature($feature),
            ]);
        }
 
        return $this->granted($feature, $subscription);
    }
 
    private function granted(string $feature, Subscription $sub): array
    {
        return [
            'allowed'      => true,
            'feature'      => $feature,
            'plan'         => $sub->plan->slug,
            'source'       => $sub->source,
            'expires_at'   => $sub->expires_at,
            'days_remaining' => $sub->daysRemaining(),
        ];
    }
 
    private function denied(string $feature, string $reason, array $extra = []): array
    {
        return array_merge([
            'allowed' => false,
            'feature' => $feature,
            'reason'  => $reason,
        ], $extra);
    }
 
    private function plansWithFeature(string $feature): array
    {
        return Plan::where('is_active', true)
            ->whereJsonContains('features', $feature)
            ->pluck('slug')
            ->toArray();
    }
}