<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiController extends Controller
{
    use ApiResponse;

    const FREE_GENERATION_LIMIT = 3;

    /**
     * Resolves authenticated user or sanctum token if available
     */
    private function resolveUser(Request $request): ?User
    {
        return $request->user('sanctum') ?? $request->user();
    }

    /**
     * Helper to compute quota for user or guest
     */
    private function calculateQuota(?User $user, string $ip): array
    {
        $isPro = false;
        $plan = 'guest';
        $totalQuota = self::FREE_GENERATION_LIMIT;

        if ($user) {
            $isPro = $user->isPro();
            $plan = $isPro ? 'pro' : 'free';
            $totalQuota = $isPro ? 999 : self::FREE_GENERATION_LIMIT;
        }

        $usedQuota = 0;
        if (Schema::hasTable('ai_generations')) {
            $query = AiGeneration::whereIn('status', ['reserved', 'consumed', 'completed']);
            if ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->whereNull('user_id')->where('ip_address', $ip);
            }
            $usedQuota = $query->count();
        }

        $remainingQuota = $isPro ? 999 : max(0, $totalQuota - $usedQuota);

        return [
            'plan'            => $plan,
            'is_pro'          => $isPro,
            'total_quota'     => $totalQuota,
            'used_quota'      => $usedQuota,
            'remaining_quota' => $remainingQuota,
            'can_generate'    => $remainingQuota > 0,
            'reset_info'      => $isPro ? 'Akses Tanpa Batas (Pro)' : '3 kali generate gratis per akun',
        ];
    }

    /**
     * GET /api/ai/quota
     * Cek sisa kuota generate AI
     */
    public function quota(Request $request)
    {
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';
        $quota = $this->calculateQuota($user, $ip);

        return $this->success($quota, 'Informasi kuota AI berhasil diambil');
    }

    /**
     * POST /api/ai/verify-ticket
     * Memvalidasi eligibility dan menerbitkan tiket sebelum generate dimulai
     */
    public function verifyTicket(Request $request)
    {
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';
        $quota = $this->calculateQuota($user, $ip);

        if (!$quota['can_generate']) {
            return $this->error(
                'Kuota generate AI gratis Anda telah habis (3/3 kali). Silakan upgrade ke paket Pro untuk membuat model 3D tanpa batas.',
                403,
                [
                    'valid'           => false,
                    'remaining_quota' => 0,
                    'upgrade_url'     => '/dashboard/pembayaran',
                ]
            );
        }

        $ticketId = 'tkt_' . Str::random(24);

        if (Schema::hasTable('ai_generations')) {
            AiGeneration::create([
                'user_id'    => $user?->id,
                'ip_address' => $ip,
                'ticket_id'  => $ticketId,
                'prompt'     => $request->input('prompt', 'Draft Model 3D'),
                'model'      => $request->input('model', 'P2-20260801'),
                'status'     => 'reserved',
            ]);
        }

        return $this->success([
            'ticket_id'       => $ticketId,
            'valid'           => true,
            'remaining_quota' => $quota['remaining_quota'],
            'plan'            => $quota['plan'],
        ], 'Tiket AI berhasil diverifikasi');
    }

    /**
     * POST /api/ai/consume
     * Mengurangi kuota / menandai tiket telah dipakai
     */
    public function consume(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|string',
        ]);

        $ticketId = $request->input('ticket_id');
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';

        if (Schema::hasTable('ai_generations')) {
            $record = AiGeneration::where('ticket_id', $ticketId)->first();
            if ($record) {
                $record->update([
                    'status'  => 'consumed',
                    'prompt'  => $request->input('prompt', $record->prompt),
                    'model'   => $request->input('model', $record->model),
                    'task_id' => $request->input('task_id', $record->task_id),
                ]);
            }
        }

        $quota = $this->calculateQuota($user, $ip);

        return $this->success([
            'ticket_id'       => $ticketId,
            'consumed'        => 1,
            'remaining_quota' => $quota['remaining_quota'],
        ], 'Kuota AI berhasil digunakan');
    }

    /**
     * POST /api/ai/refund
     * Mengembalikan kuota jika proses generate dibatalkan atau gagal
     */
    public function refund(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|string',
        ]);

        $ticketId = $request->input('ticket_id');
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';

        if (Schema::hasTable('ai_generations')) {
            $record = AiGeneration::where('ticket_id', $ticketId)->first();
            if ($record && in_array($record->status, ['reserved', 'consumed'])) {
                $record->update([
                    'status'        => 'refunded',
                    'refund_reason' => $request->input('reason', 'Generate cancelled or failed'),
                ]);
            }
        }

        $quota = $this->calculateQuota($user, $ip);

        return $this->success([
            'ticket_id'       => $ticketId,
            'refunded'        => 1,
            'remaining_quota' => $quota['remaining_quota'],
        ], 'Kuota AI berhasil dikembalikan');
    }

    /**
     * GET /api/ai/history
     * Mengambil riwayat generasi AI user
     */
    public function history(Request $request)
    {
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';

        $items = [];
        if (Schema::hasTable('ai_generations')) {
            $query = AiGeneration::whereIn('status', ['completed', 'consumed'])
                ->whereNotNull('model_url');

            if ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->whereNull('user_id')->where('ip_address', $ip);
            }

            $items = $query->latest()->take(20)->get()->map(function ($item) {
                return [
                    'id'          => $item->ticket_id ?? (string)$item->id,
                    'prompt'      => $item->prompt,
                    'model'       => $item->model ?? 'P2-20260801',
                    'modelUrl'    => $item->model_url,
                    'previewUrl'  => $item->preview_url,
                    'createdAt'   => $item->created_at?->toISOString() ?? now()->toISOString(),
                ];
            });
        }

        return $this->success($items, 'Riwayat AI berhasil diambil');
    }

    /**
     * POST /api/ai/history
     * Menyimpan hasil akhir model 3D AI ke database
     */
    public function storeHistory(Request $request)
    {
        $request->validate([
            'prompt'    => 'required|string',
            'model_url' => 'required|string',
        ]);

        $ticketId = $request->input('ticket_id') ?? ('tkt_' . Str::random(24));
        $user = $this->resolveUser($request);
        $ip = $request->ip() ?? '127.0.0.1';

        if (Schema::hasTable('ai_generations')) {
            $record = AiGeneration::where('ticket_id', $ticketId)->first();
            if ($record) {
                $record->update([
                    'status'      => 'completed',
                    'prompt'      => $request->input('prompt'),
                    'model'       => $request->input('model', 'P2-20260801'),
                    'task_id'     => $request->input('task_id'),
                    'model_url'   => $request->input('model_url'),
                    'preview_url' => $request->input('preview_url'),
                ]);
            } else {
                AiGeneration::create([
                    'user_id'     => $user?->id,
                    'ip_address'  => $ip,
                    'ticket_id'   => $ticketId,
                    'prompt'      => $request->input('prompt'),
                    'model'       => $request->input('model', 'P2-20260801'),
                    'task_id'     => $request->input('task_id'),
                    'model_url'   => $request->input('model_url'),
                    'preview_url' => $request->input('preview_url'),
                    'status'      => 'completed',
                ]);
            }
        }

        return $this->success(['ticket_id' => $ticketId], 'Riwayat AI berhasil disimpan', 201);
    }
}

