<?php 

namespace App\Http\Controllers\Auth;
 
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Service\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, RateLimiter};
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

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|same:password',
        ], [
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'password.min' => 'Password minimal 8 karakter',
            'password.mixed_case' => 'Password harus mengandung huruf besar dan kecil',
            'password.numbers' => 'Password harus mengandung angka',
            'password.symbols' => 'Password harus mengandung simbol (!@#$%^&*)',
            'password_confirmation.same' => 'Konfirmasi password tidak sesuai',
        ]);

        try {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => $validated['password'],
                'status'   => 'active',
            ]);
 
            AuditLog::record(
                event:     'auth.registered',
                entity:    $user,
                newValues: [
                    'email'          => $user->email,
                    'domain'         => $request->detected_domain,
                    'is_whitelisted' => $request->is_whitelisted,
                ],
                reason: 'User baru mendaftar'
            );
 
            $subscription = $this->subscriptionService->assignOnRegister($user);
    
            $deviceName = $request->input('device_name', $request->userAgent() ?? 'unknown-device');
            $token = $user->createToken($deviceName)->plainTextToken;
    
            return response()->json([
                'message'      => 'Registrasi berhasil!',
                'token'        => $token,
                'user'         => $user->load('activeSubscription.plan'),
                'subscription' => [
                    'plan'         => $subscription->plan->slug,
                    'source'       => $subscription->source,
                    'is_pro'       => $user->isPro(),
                    'pro_reason'   => $subscription->source === 'instansi'
                        ? 'Akun Pro diberikan otomatis karena email instansi!'
                        : null,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat akun',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
 
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->input('email');
        $throttleKey = 'login:' . $email . '|' . $request->ip();

        // Layer 1: Rate limit per email+IP 
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            AuditLog::create([
                'event'       => 'auth.login_rate_limited',
                'entity_type' => 'User',
                'entity_id'   => 0,
                'new_values'  => ['email' => $email],
                'reason'      => 'Terlalu banyak percobaan login dalam waktu singkat',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $user = User::where('email', $email)->first();

        // Layer 2: Account lockout
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $remainingMinutes = now()->diffInMinutes($user->locked_until, false);

            AuditLog::record(
                event:  'auth.login_blocked_locked',
                entity: $user,
                reason: 'Login ditolak karena akun sedang terkunci',
            );

            throw ValidationException::withMessages([
                'email' => "Akun terkunci karena terlalu banyak percobaan gagal. Coba lagi dalam {$remainingMinutes} menit.",
            ]);
        }

        // Validasi kredensial 
        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY);

            if ($user) {
                $user->increment('failed_login_attempts');

                if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $user->update([
                        'locked_until'          => now()->addMinutes(self::LOCKOUT_MINUTES),
                        'failed_login_attempts' => 0,
                    ]);

                    AuditLog::record(
                        event:  'auth.account_locked',
                        entity: $user,
                        reason: 'Akun dikunci otomatis setelah gagal login berulang',
                    );
                }
            }

            AuditLog::create([
                'event'       => 'auth.login_failed',
                'entity_type' => 'User',
                'entity_id'   => $user->id ?? 0,
                'new_values'  => ['email' => $email],
                'reason'      => 'Kredensial tidak valid',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        // Login sukses
        RateLimiter::clear($throttleKey);

        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until'           => null,
            ]);
        }

        $deviceName = $request->input('device_name', $request->userAgent() ?? 'unknown-device');
        $token = $user->createToken($deviceName)->plainTextToken;

        AuditLog::record(
            event:  'auth.login_success',
            entity: $user,
            reason: 'User berhasil login'
        );
 
        return response()->json([
            'token' => $token,
            'user'  => $user->load('activeSubscription.plan'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        AuditLog::record(
            event:  'auth.logout',
            entity: $request->user(),
            reason: 'User logout dari device ini',
        );

        return response()->json(['message' => 'Berhasil logout dari device ini']);
    }

    public function logoutAllDevices(Request $request)
    {
        $user = $request->user();
        $tokenCount = $user->tokens()->count();

        $user->tokens()->delete();

        AuditLog::record(
            event:     'auth.logout_all_devices',
            entity:    $user,
            newValues: ['devices_logged_out' => $tokenCount],
            reason:    'User logout dari semua device',
        );

        return response()->json(['message' => "Berhasil logout dari {$tokenCount} device"]);
    }
 
    public function me(Request $request)
    {
        $user = $request->user()->load('activeSubscription.plan');
 
        return response()->json([
            'user'        => $user,
            'permissions' => [
                'is_pro'         => $user->isPro(),
                'is_on_trial'    => $user->isOnTrial(),
                'days_remaining' => $user->daysRemaining(),
                'features'       => $user->activeSubscription?->plan->features ?? [],
            ],
        ]);
    }
}