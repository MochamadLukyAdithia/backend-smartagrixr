<?php

use App\Services\PermissionService;
 
class PermissionController extends Controller
{
    public function __construct(private PermissionService $permissionService) {}
 
    /**
     * Frontend check permission sebelum tampilkan fitur
     * GET /api/permission/check?feature=upload_3d_asset
     */
    public function check(Request $request)
    {
        $request->validate(['feature' => 'required|string']);
 
        $result = $this->permissionService->check(
            $request->user(),
            $request->input('feature')
        );
 
        return response()->json($result, $result['allowed'] ? 200 : 403);
    }
}