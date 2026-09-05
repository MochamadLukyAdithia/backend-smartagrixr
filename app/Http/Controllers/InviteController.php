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
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        return response()->json(
            $this->classroomService->getInviteData($classroom)
        );
    }

    // GET /api/classrooms/resolve/{code}
    // FE panggil ini untuk validasi kode sebelum join
    public function resolve(string $code)
    {
        $classroom = Classroom::where('invite_code', strtoupper($code))
            ->where('is_active', true)
            ->select(['id', 'name', 'subject', 'teacher_id'])
            ->with('teacher:id,name,avatar')
            ->first();

        if (!$classroom) {
            return response()->json([
                'valid'   => false,
                'message' => 'Kode kelas tidak ditemukan atau tidak aktif',
            ], 404);
        }

        return response()->json([
            'valid'     => true,
            'classroom' => [
                'id'      => $classroom->id,
                'name'    => $classroom->name,
                'subject' => $classroom->subject,
                'teacher' => $classroom->teacher->name,
            ],
        ]);
    }

    // POST /api/classrooms/join/{code}
    // FE panggil ini setelah user konfirmasi join
    public function joinByCode(Request $request, string $code)
    {
        try {
            $classroom = $this->classroomService->enrollByCode(
                $request->user(),
                $code
            );

            return response()->json([
                'message'      => "Berhasil bergabung ke kelas {$classroom->name}!",
                'classroom_id' => $classroom->id,
                'redirect'     => "/classroom/{$classroom->id}",
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
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
        // Redirect ke login jika belum auth
        if (!auth()->check()) {
            return redirect("/login?redirect=/join/{$code}");
        }
 
        try {
            $classroom = $this->classroomService->enrollByCode($request->user(), $code);
 
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