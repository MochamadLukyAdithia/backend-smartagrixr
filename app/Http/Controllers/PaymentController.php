<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog, Payment, Plan};
use App\Services\{PaymentService, SubscriptionService};
use Illuminate\Http\Request;
 
class PaymentController extends Controller
{
    public function __construct(
        private PaymentService      $paymentService,
        private SubscriptionService $subscriptionService,
    ) {}
 
    /**
     * Step 1: User klik upgrade → dapat Snap Token
     */
    public function initiate(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($request->plan_id);
        $user = $request->user();

        if ($plan->isFree()) {
            return response()->json(['message' => 'Tidak perlu bayar untuk plan Free'], 400);
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

        return response()->json([
            'snap_token' => $result['snap_token'],
            'order_id'   => $result['order_id'],
            'amount'     => $plan->price,
            'plan'       => $plan->name,
        ]);
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
 
        return response()->json($payments);
    }
}