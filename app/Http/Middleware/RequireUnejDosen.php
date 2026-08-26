<?php

namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
 
class RequireUnejDosen
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();
 
        if (!$user->is_unej_verified) {
            return response()->json([
                'message'  => 'Fitur ini hanya untuk civitas UNEJ. Silakan login menggunakan akun Google UNEJ (@mail.unej.ac.id).',
                'code'     => 'UNEJ_ACCOUNT_REQUIRED',
                'help_url' => 'https://sso.unej.ac.id',
            ], 403);
        }
 
        if ($user->unej_role !== 'dosen') {
            return response()->json([
                'message' => 'Fitur ini hanya untuk dosen UNEJ.',
                'code'    => 'DOSEN_ROLE_REQUIRED',
                'role'    => $user->unej_role,
            ], 403);
        }
 
        return $next($request);
    }
}