<?php

use App\Models\AuditLog;
 
class AuditLogController extends Controller
{
    /**
     * List audit log dengan filter
     * GET /api/admin/audit-logs?event=subscription&user_id=1
     */
    public function index(Request $request)
    {
        $logs = AuditLog::with(['user', 'actor'])
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->event,   fn($q) => $q->where('event', 'like', "%{$request->event}%"))
            ->when($request->from,    fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,      fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate(20);
 
        return response()->json($logs);
    }
 
    /**
     * Audit log spesifik user
     * GET /api/admin/audit-logs/user/1
     */
    public function forUser(int $userId)
    {
        $logs = AuditLog::with('actor')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->groupBy(fn($log) => $log->created_at->format('Y-m-d'));
 
        return response()->json($logs);
    }
}