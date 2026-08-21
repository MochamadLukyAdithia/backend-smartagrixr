<?php

namespace App\Services;

use App\Models\{Payment, Plan, User, AuditLog};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{Http, Log, DB};

class PaymentService
{
    private string $serverKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->baseUrl   = config('midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    /**
     * Buat order baru & dapatkan Snap Token dari Midtrans
     */
    public function createSnapToken(User $user, Plan $plan): array
    {
        $orderId = $this->generateOrderId();

        // Simpan payment ke DB dulu (status: pending)
        $payment = Payment::create([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'order_id'   => $orderId,
            'amount'     => $plan->price,
            'status'     => 'pending',
            'expired_at' => now()->addHours(24),
        ]);

        // Kirim ke Midtrans
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $plan->price,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone ?? '',
            ],
            'item_details' => [
                [
                    'id'       => $plan->slug,
                    'price'    => $plan->price,
                    'quantity' => 1,
                    'name'     => "SmartAgri XR - {$plan->name}",
                ],
            ],
            'callbacks' => [
                'finish' => config('app.url') . '/payment/finish',
            ],
        ];

        try {
            $response = $this->callMidtrans('/transactions', $payload);
        } catch (\Exception $e) {
            $payment->update(['status' => 'failed']);
            AuditLog::record(
                event:     'payment.failed',
                entity:    $payment,
                oldValues: [],
                newValues: ['error' => $e->getMessage()],
                reason:    'Gagal request ke Midtrans',
            );
            throw $e;
        }

        if (empty($response['token'])) {
            $payment->update(['status' => 'failed']);
            AuditLog::record(
                event:     'payment.failed',
                entity:    $payment,
                oldValues: [],
                newValues: ['error' => 'Snap token kosong di response Midtrans'],
                reason:    'Response Midtrans tidak valid',
            );
            throw new \Exception('Snap token tidak diterima dari Midtrans');
        }

        // Simpan snap token
        $payment->update(['snap_token' => $response['token']]);

        AuditLog::record(
            event:     'payment.initiated',
            entity:    $payment,
            oldValues: [],
            newValues: ['order_id' => $orderId, 'amount' => $plan->price, 'plan' => $plan->slug],
            reason:    "User memulai pembayaran untuk plan {$plan->name}",
        );

        return [
            'snap_token' => $response['token'],
            'order_id'   => $orderId,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * Handle webhook/callback dari Midtrans
     */
    public function handleWebhook(array $payload): Payment
    {
        // Validasi field wajib ada sebelum diproses
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \Exception("Payload webhook tidak lengkap: field '{$field}' hilang");
            }
        }

        // Verifikasi signature Midtrans (harus dilakukan sebelum sentuh DB)
        $this->verifySignature($payload);

        return DB::transaction(function () use ($payload) {
            $payment = Payment::where('order_id', $payload['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency guard: kalau status sudah final, jangan diproses ulang
            if (in_array($payment->status, ['success', 'refunded', 'expired'], true)) {
                AuditLog::record(
                    event:     'payment.webhook_ignored',
                    entity:    $payment,
                    oldValues: ['status' => $payment->status],
                    newValues: ['incoming_status' => $payload['transaction_status']],
                    reason:    'Webhook diterima setelah status final, diabaikan untuk mencegah reprocessing',
                );
                return $payment;
            }

            // Validasi nominal: cegah manipulasi/mismatch amount
            $incomingAmount = (float) $payload['gross_amount'];
            $expectedAmount = (float) $payment->amount;

            if (abs($incomingAmount - $expectedAmount) > 0.01) {
                AuditLog::record(
                    event:     'payment.amount_mismatch',
                    entity:    $payment,
                    oldValues: [],
                    newValues: ['expected' => $expectedAmount, 'received' => $incomingAmount],
                    reason:    'Nominal webhook tidak cocok dengan payment record',
                );
                throw new \Exception('Amount mismatch untuk order_id: ' . $payload['order_id']);
            }

            $oldStatus = $payment->status;
            $fraudStatus = $payload['fraud_status'] ?? null;

            // Map status Midtrans ke status kita, dengan pengecekan fraud_status untuk capture
            $newStatus = match (true) {
                $payload['transaction_status'] === 'capture' && $fraudStatus === 'accept'    => 'success',
                $payload['transaction_status'] === 'capture' && $fraudStatus === 'challenge' => 'challenge',
                $payload['transaction_status'] === 'capture' && $fraudStatus === 'deny'      => 'failed',
                $payload['transaction_status'] === 'settlement'                              => 'success',
                $payload['transaction_status'] === 'pending'                                 => 'pending',
                in_array($payload['transaction_status'], ['deny', 'cancel'], true)            => 'failed',
                $payload['transaction_status'] === 'expire'                                   => 'expired',
                $payload['transaction_status'] === 'refund'                                   => 'refunded',
                default                                                                       => 'pending',
            };

            $payment->update([
                'status'           => $newStatus,
                'external_id'      => $payload['transaction_id'] ?? null,
                'payment_method'   => $payload['payment_type'] ?? null,
                'gateway_response' => $payload,
                'paid_at'          => $newStatus === 'success' ? now() : $payment->paid_at,
            ]);

            AuditLog::record(
                event:     "payment.{$newStatus}",
                entity:    $payment,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status'         => $newStatus,
                    'payment_method' => $payload['payment_type'] ?? null,
                    'external_id'    => $payload['transaction_id'] ?? null,
                ],
                reason:    "Webhook Midtrans: {$payload['transaction_status']}",
                metadata:  ['fraud_status' => $fraudStatus],
            );

            return $payment;
        });
    }

    /**
     * Verifikasi signature dari Midtrans
     */
    private function verifySignature(array $payload): void
    {
        $signature = hash(
            'sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            $this->serverKey
        );

        if (!hash_equals($signature, $payload['signature_key'])) {
            throw new \Exception('Invalid Midtrans signature');
        }
    }

    /**
     * Call Midtrans API
     */
    private function callMidtrans(string $endpoint, array $payload): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(10)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl . $endpoint, $payload);

        if ($response->failed()) {
            Log::error('Midtrans API error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \Exception('Midtrans API error (status ' . $response->status() . ')');
        }

        return $response->json();
    }

    /**
     * Generate unique order ID
     */
    private function generateOrderId(): string
    {
        do {
            $orderId = 'SXAR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Payment::where('order_id', $orderId)->exists());

        return $orderId;
    }
}