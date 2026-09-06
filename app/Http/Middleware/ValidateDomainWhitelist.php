<?php

namespace App\Http\Middleware;
 
use App\Models\DomainWhitelist;
use Closure;
use Illuminate\Http\Request;
 
class ValidateDomainWhitelist
{
    /**
     * Hanya deteksi domain, TIDAK blokir.
     * Semua user boleh daftar.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $email = $request->input('email');
 
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $domain = DomainWhitelist::extractDomain($email);
 
            // Inject info domain ke request
            $request->merge([
                'detected_domain' => $domain,
                'is_whitelisted'  => DomainWhitelist::isWhitelisted($domain),
            ]);
        }
 
        return $next($request);
    }
}
