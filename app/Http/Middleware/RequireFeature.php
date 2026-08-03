<?php

use App\Services\PermissionService;
 
class RequireFeature
{
    public function __construct(private PermissionService $permission) {}
 
    /**
     * Pakai di route: ->middleware('feature:upload_3d_asset')
     */
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $result = $this->permission->check(auth()->user(), $feature);
 
        if (!$result['allowed']) {
            return response()->json([
                'message'     => 'Akses ditolak: ' . $result['reason'],
                'feature'     => $feature,
                'upgrade_url' => $result['upgrade_url'] ?? '/pricing',
            ], 403);
        }
 
        // Inject info permission ke request
        $request->merge(['permission' => $result]);
 
        return $next($request);
    }
}