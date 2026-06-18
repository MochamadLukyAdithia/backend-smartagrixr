<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private array $allowedProviders = ['google', 'apple'];

    public function redirect(string $provider)
    {
        abort_if(!in_array($provider, $this->allowedProviders), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_if(!in_array($provider, $this->allowedProviders), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error'   => $e->getMessage(),
            ], 401);
        }

        $user = User::updateOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'              => $socialUser->getName() ?? 'User',
                'provider'          => $provider,
                'provider_id'       => $socialUser->getId(),
                'avatar'            => $socialUser->getAvatar(),
                'email_verified_at' => now(),
                'password'          => bcrypt(Str::random(32)),
            ]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        // redirect to the frontend using a token
        return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);
    }
}
