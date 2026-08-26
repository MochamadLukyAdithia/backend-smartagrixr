<?php

namespace App\Http\Controllers\Auth;
 
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\{SubscriptionService, UnejRoleDetector};
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
 
class SocialAuthController extends Controller
{
    private array $allowedProviders = ['google'];
 
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}
 
    public function redirect(string $provider)
    {
        abort_if(!in_array($provider, $this->allowedProviders), 404);
        return Socialite::driver($provider)->stateless()->redirect();
    }
 
    public function callback(string $provider)
    {
        abort_if(!in_array($provider, $this->allowedProviders), 404);
 
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed'], 401);
        }
 
        $email     = $socialUser->getEmail();
        $isUnej    = UnejRoleDetector::isUnejEmail($email);
        $unejRole  = UnejRoleDetector::detect($email);
 
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $socialUser->getName() ?? 'User',
                'provider'          => $provider,
                'provider_id'       => $socialUser->getId(),
                'avatar'            => $socialUser->getAvatar(),
                'email_verified_at' => now(),
                'password'          => bcrypt(Str::random(32)),
                'unej_role'         => $unejRole,
                'is_unej_verified'  => $isUnej,
                'status'            => 'active',
            ]
        );
 
        // Assign subscription hanya saat pertama daftar
        if ($user->wasRecentlyCreated) {
            $this->subscriptionService->assignOnRegister($user);
        }
 
        $token = $user->createToken('auth_token')->plainTextToken; 
        $params = http_build_query([
            'token'     => $token,
            'role'      => $unejRole,
            'is_unej'   => $isUnej ? '1' : '0',
        ]);
 
        return redirect(env('FRONTEND_URL') . '/auth/callback?' . $params);
    }
}
