<?php 

namespace App\Http\Controllers\Auth;
 
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\SubscriptionService;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
 
class AuthController extends Controller
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;
    private const THROTTLE_DECAY  = 60;

    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * POST /api/register
     * Step 1: Daftar → kirim email verifikasi
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'username'              => 'required|string|min:4|max:15|regex:/^[a-z0-9_]+$/|unique:users,username',
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|same:password',
        ], [
            'email.unique'                => 'Email sudah terdaftar',
            'username.unique'             => 'Username sudah digunakan',
            'username.min'                => 'Username minimal 4 karakter',
            'username.max'                => 'Username maksimal 15 karakter',
            'username.regex'              => 'Username hanya boleh huruf kecil, angka, dan underscore',
            'password_confirmation.same'  => 'Konfirmasi password tidak sesuai',
        ]);
 
        try {
            // Generate token verifikasi
            $verificationToken = Str::random(64);
 
            // Buat user dengan status unverified
            $user = User::create([
                'name'                          => $validated['name'],
                'email'                         => $validated['email'],
                'username'                      => $validated['username'],
                'password'                      => $validated['password'],
                'status'                        => 'unverified', // belum bisa login jika
                'email_verified_at'             => null,
                'email_verification_token'      => $verificationToken,
                'email_verification_sent_at'    => now(),
            ]);
 
            // Kirim email verifikasi
            $user->notify(new VerifyEmailNotification(
                token:    $verificationToken,
                userName: $user->name,
            ));
 
            AuditLog::record(
                event:     'auth.registered',
                entity:    $user,
                newValues: ['email' => $user->email, 'status' => 'unverified'],
                reason:    'User baru mendaftar, menunggu verifikasi email',
            );
 
            return response()->json([
                'message' => 'Registrasi berhasil! Cek email kamu untuk verifikasi.',
                'email'   => $user->email,
                // Tidak kirim token dulu — user harus verifikasi email dulu
            ], 201);
 
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat akun',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
 
    /**
     * POST /api/auth/verify-email
     * Step 2: Verifikasi email dengan token
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);
 
        $user = User::where('email', $request->email)
            ->where('email_verification_token', $request->token)
            ->first();
 
        // Token tidak valid
        if (!$user) {
            return response()->json([
                'message' => 'Link verifikasi tidak valid atau sudah digunakan.',
                'code'    => 'INVALID_TOKEN',
            ], 422);
        }
 
        // Token expired (lebih dari 24 jam)
        if ($user->email_verification_sent_at->lt(now()->subHours(24))) {
            return response()->json([
                'message'    => 'Link verifikasi sudah kedaluwarsa. Minta link baru.',
                'code'       => 'TOKEN_EXPIRED',
                'can_resend' => true,
            ], 422);
        }
 
        // Sudah diverifikasi sebelumnya
        if ($user->email_verified_at) {
            // Tetap berikan token agar langsung bisa login
            $token = $user->createToken($request->userAgent() ?? 'web')->plainTextToken;
 
            return response()->json([
                'message'  => 'Email sudah terverifikasi sebelumnya.',
                'token'    => $token,
                'redirect' => '/dashboard',
            ]);
        }
 
        // ── Verifikasi berhasil ──
        $user->update([
            'email_verified_at'          => now(),
            'email_verification_token'   => null, // hapus token setelah dipakai
            'email_verification_sent_at' => null,
            'status'                     => 'active',
        ]);
 
        // Assign subscription setelah verifikasi
        $subscription = $this->subscriptionService->assignOnRegister($user);
 
        // Generate token login
        $token = $user->createToken($request->userAgent() ?? 'web')->plainTextToken;
 
        AuditLog::record(
            event:     'auth.email_verified',
            entity:    $user,
            newValues: ['status' => 'active'],
            reason:    'Email berhasil diverifikasi',
        );
 
        return response()->json([
            'message'      => 'Email berhasil diverifikasi! Selamat datang di SmartAgri XR 🌾',
            'token'        => $token,
            'user'         => $user->load('activeSubscription.plan'),
            'subscription' => [
                'plan'       => $subscription->plan->slug,
                'source'     => $subscription->source,
                'is_pro'     => $user->isPro(),
                'pro_reason' => $subscription->source === 'instansi'
                    ? 'Akun Pro diberikan otomatis karena email instansi!'
                    : null,
            ],
            'redirect' => '/dashboard',
        ]);
    }
 
    /**
     * POST /api/auth/resend-verification
     * Kirim ulang email verifikasi
     */
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);
 
        // Rate limit: max 3x per 10 menit per email
        $throttleKey = 'resend-verify:' . $request->email;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }
        RateLimiter::hit($throttleKey, 600); // 10 menit
 
        $user = User::where('email', $request->email)
            ->whereNull('email_verified_at')
            ->first();
 
        // Selalu return success (jangan bocorkan apakah email ada atau tidak)
        if (!$user) {
            return response()->json([
                'message' => 'Jika email terdaftar dan belum terverifikasi, link baru telah dikirim.',
            ]);
        }
 
        // Generate token baru
        $newToken = Str::random(64);
        $user->update([
            'email_verification_token'   => $newToken,
            'email_verification_sent_at' => now(),
        ]);
 
        $user->notify(new VerifyEmailNotification(
            token:    $newToken,
            userName: $user->name,
        ));
 
        AuditLog::record(
            event:  'auth.verification_resent',
            entity: $user,
            reason: 'User meminta kirim ulang email verifikasi',
        );
 
        return response()->json([
            'message' => 'Link verifikasi baru telah dikirim ke email kamu.',
        ]);
    }
 
    /**
     * POST /api/login
     * Cek verifikasi sebelum login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
 
        $email       = $request->input('email');
        $throttleKey = 'login:' . $email . '|' . $request->ip();
 
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }
 
        $user = User::where('email', $email)->first();
 
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $minutes = now()->diffInMinutes($user->locked_until, false);
            throw ValidationException::withMessages([
                'email' => "Akun terkunci. Coba lagi dalam {$minutes} menit.",
            ]);
        }
 
        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY);
 
            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $user->update([
                        'locked_until'          => now()->addMinutes(self::LOCKOUT_MINUTES),
                        'failed_login_attempts' => 0,
                    ]);
                }
            }
 
            return response()->json(['message' => 'Email atau password salah'], 401);
        }
 
        // ── Cek email sudah diverifikasi ──
        if (!$user->email_verified_at) {
            return response()->json([
                'message'    => 'Email belum diverifikasi. Cek inbox kamu atau minta link baru.',
                'code'       => 'EMAIL_NOT_VERIFIED',
                'email'      => $user->email,
                'can_resend' => true,
            ], 403);
        }
 
        // ── Cek status akun ──
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun tidak aktif. Hubungi administrator.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }
 
        // Login sukses
        RateLimiter::clear($throttleKey);
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
 
        $deviceName = $request->input('device_name', $request->userAgent() ?? 'unknown-device');
        $token      = $user->createToken($deviceName)->plainTextToken;
 
        AuditLog::record(
            event:  'auth.login_success',
            entity: $user,
            reason: 'User berhasil login',
        );
 
        return response()->json([
            'token' => $token,
            'user'  => $user->load('activeSubscription.plan'),
        ]);
    }
 
    public function me(Request $request)
    {
        $user = $request->user()->load('activeSubscription.plan');
 
        return response()->json([
            'user'        => $user,
            'unej' => [
                'is_unej'        => $user->isUnejCivitas(),
                'role'           => $user->unej_role,
                'is_verified'    => $user->is_unej_verified,
                'can_create_class' => $user->isDosen(),
                'can_join_class'   => true,
            ],
            'permissions' => [
                'is_pro'         => $user->isPro(),
                'is_on_trial'    => $user->isOnTrial(),
                'days_remaining' => $user->daysRemaining(),
                'features'       => $user->activeSubscription?->plan->features ?? [],
            ],
        ]);
    }
}