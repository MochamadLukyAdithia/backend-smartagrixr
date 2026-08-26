<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Services\ClassroomService;
use Illuminate\Http\Request;
class InviteController extends Controller
{
    public function __construct(private ClassroomService $classroomService) {}
 
    // GET /classrooms/{id}/invite-code — lihat kode saat ini
    public function show(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa lihat invite code'], 403);
        }
 
        return response()->json([
            'invite_code' => $classroom->invite_code,
            'join_url'    => url("/join/{$classroom->invite_code}"),
        ]);
    }
 
    // POST /classrooms/{id}/invite-code/regenerate — generate kode baru
    public function regenerate(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa regenerate invite code'], 403);
        }
 
        $newCode = $this->classroomService->regenerateInviteCode($classroom);
 
        return response()->json([
            'invite_code' => $newCode,
            'join_url'    => url("/join/{$newCode}"),
            'message'     => 'Kode lama sudah tidak berlaku',
        ]);
    }
 
    // GET /join/{code} — deep link join kelas
    public function join(Request $request, string $code)
    {
        // Jika Bearer token ada di header Authorization, gunakan auth:sanctum
        $token = $request->bearerToken();
        if ($token) {
            // API request dengan Bearer token
            try {
                // Manual auth check untuk Bearer token
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
                
                if (!$user) {
                    return response()->json(['message' => 'Token tidak valid'], 401);
                }
                
                $classroom = $this->classroomService->enrollByCode($user, $code);

                return response()->json([
                    'message'   => "Berhasil bergabung ke kelas: {$classroom->name}",
                    'classroom' => $classroom,
                ], 200);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['message' => 'Kode kelas tidak ditemukan atau tidak aktif'], 404);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
        }
        
        // Browser request tanpa token — redirect ke login
        if (!auth()->check()) {
            return redirect("/login?redirect=/join/{$code}");
        }

        try {
            $classroom = $this->classroomService->enrollByCode(auth()->user(), $code);

            return response()->json([
                'message'   => "Berhasil bergabung ke kelas: {$classroom->name}",
                'classroom' => $classroom,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Kode kelas tidak ditemukan atau tidak aktif'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}