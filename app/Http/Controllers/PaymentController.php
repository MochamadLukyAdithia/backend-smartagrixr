<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog, Payment, Plan};
use App\Services\{PaymentService, SubscriptionService};
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
 
class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PaymentService      $paymentService,
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * GET /api/plans — list semua plan aktif
     */
    public function showPlans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                return [
                    'id'            => $plan->id,
                    'name'          => $plan->name,
                    'slug'          => $plan->slug,
                    'description'   => $plan->description,
                    'price'         => $plan->price,
                    'price_formatted' => $plan->formattedPrice(),
                    'billing_cycle' => $plan->billing_cycle,
                    'max_assets'    => $plan->max_assets,
                    'max_storage_mb'=> $plan->max_storage_mb,
                    'max_classes'   => $plan->max_classes,
                    'features'      => is_string($plan->features)
                        ? json_decode($plan->features, true)
                        : $plan->features,
                ];
            });

        return $this->success(['data' => $plans], 'Daftar plan berhasil diambil');
    }

    /**
     * GET /api/plans/{slug} — detail satu plan
     */
    public function detailPlan(string $slug)
    {
        $plan = Plan::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return $this->notFound('Plan tidak ditemukan');
        }

        return $this->success([
            'id'              => $plan->id,
            'name'            => $plan->name,
            'slug'            => $plan->slug,
            'description'     => $plan->description,
            'price'           => $plan->price,
            'price_formatted' => $plan->formattedPrice(),
            'billing_cycle'   => $plan->billing_cycle,
            'max_assets'      => $plan->max_assets,
            'max_storage_mb'  => $plan->max_storage_mb,
            'max_classes'     => $plan->max_classes,
            'features'        => is_string($plan->features)
                ? json_decode($plan->features, true)
                : $plan->features,
        ], 'Detail plan berhasil diambil');
    }
 
    /**
     * Step 1: User klik upgrade → dapat Snap Token
     */
    public function initiate(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);
        $user = $request->user();

        if ($plan->isFree()) {
            return $this->error('Tidak perlu bayar untuk plan Free', 400);
        }

        // Cek apakah masih ada pending payment yang belum expired untuk plan yang sama
        $existing = Payment::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->where('expired_at', '>', now())
            ->latest()
            ->first();

        if ($existing && $existing->snap_token) {
            return response()->json([
                'snap_token' => $existing->snap_token,
                'order_id'   => $existing->order_id,
                'amount'     => $plan->price,
                'plan'       => $plan->name,
            ]);
        }

        $result = $this->paymentService->createSnapToken($user, $plan);

        return $this->success([
            'snap_token' => $result['snap_token'],
            'order_id'   => $result['order_id'],
            'amount'     => $plan->price,
            'plan'       => $plan->name,
        ], 'Snap token berhasil dibuat');
    }
 
    /**
     * Step 2: Webhook dari Midtrans setelah payment selesai
     */
    public function webhook(Request $request)
    {
        try {
            $payment = $this->paymentService->handleWebhook($request->all());

            if ($payment->isSuccess() && !$payment->subscription_id) {
                try {
                    $this->subscriptionService->upgradeAfterPayment(
                        user:    $payment->user,
                        plan:    $payment->plan,
                        payment: $payment,
                    );
                } catch (\Throwable $e) {
                    \Log::critical('Upgrade subscription gagal setelah payment sukses', [
                        'payment_id' => $payment->id,
                        'order_id'   => $payment->order_id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return response()->json(['status' => 'ok']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::warning('Webhook order_id tidak ditemukan', [
                'order_id' => $request->input('order_id'),
            ]);

            return response()->json(['status' => 'ok', 'note' => 'order not found'], 200);

        } catch (\Exception $e) {
            \Log::error('Midtrans webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
 
    /**
     * Riwayat pembayaran user
     */
    public function history(Request $request)
    {
        $payments = $request->user()
            ->payments()
            ->with('plan')
            ->latest()
            ->paginate(10);
 
        return $this->success($payments, 'Riwayat pembayaran berhasil diambil');
    }
}
