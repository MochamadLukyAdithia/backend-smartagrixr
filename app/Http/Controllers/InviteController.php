<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Services\ClassroomService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    use ApiResponse;

    public function __construct(private ClassroomService $classroomService) {}
 
    // GET /classrooms/{id}/invite-code — lihat kode saat ini
    public function show(Request $request, int $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Classroom tidak ditemukan');
        }

        if (!$classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa melihat kode invite');
        }

        return $this->success(
            $this->classroomService->getInviteData($classroom),
            'Data invite berhasil diambil'
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
            return $this->error('Kode kelas tidak ditemukan atau tidak aktif', 404);
        }

        return $this->success([
            'id'      => $classroom->id,
            'name'    => $classroom->name,
            'subject' => $classroom->subject,
            'teacher' => $classroom->teacher->name,
        ], 'Informasi kelas berhasil diambil');
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

            return $this->success([
                'classroom_id' => $classroom->id,
                'redirect'     => "/classroom/{$classroom->id}",
            ], "Berhasil bergabung ke kelas {$classroom->name}!");
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
 
    // POST /classrooms/{id}/invite-code/regenerate — generate kode baru
    public function regenerate(Request $request, int $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Classroom tidak ditemukan');
        }
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa membuat kode invite baru');
        }
 
        $newCode = $this->classroomService->regenerateInviteCode($classroom);
 
        return $this->success([
            'invite_code' => $newCode,
            'join_url'    => url("/join/{$newCode}"),
        ], 'Kode lama sudah tidak berlaku');
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
 
            return $this->success($classroom, "Berhasil bergabung ke kelas: {$classroom->name}");
 
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFound('Kode kelas tidak ditemukan atau tidak aktif');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}