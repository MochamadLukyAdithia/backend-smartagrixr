<?php

namespace App\Http\Controllers;
 
use App\Models\Classroom;
use App\Services\ClassroomService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
 
class ClassroomController extends Controller
{
    use ApiResponse;

    public function __construct(private ClassroomService $classroomService) {}
 
    // GET /classrooms — list kelas (guru: miliknya, siswa: yang diikuti)
    public function index(Request $request)
    {
        $user = $request->user();
 
        // Kelas sebagai guru
        $asTeacher = Classroom::where('teacher_id', $user->id)
            ->withCount('students')
            ->latest()
            ->get();
 
        // Kelas sebagai siswa
        $asStudent = $user->enrolledClassrooms()
            ->withCount('students')
            ->latest()
            ->get();
 
        return $this->success([
            'as_teacher' => $asTeacher,
            'as_student' => $asStudent,
        ], 'Daftar kelas berhasil diambil');
    }
 
    // POST /classrooms
    public function store(Request $request)
    {
        $user = $request->user();
    
        if (!$user->isDosen()) {
            return $this->error(
                match(true) {
                    $user->isUmum()      => 'Buat kelas hanya untuk dosen UNEJ. Login dengan akun Google UNEJ (@mail.unej.ac.id).',
                    $user->isMahasiswa() => 'Mahasiswa tidak bisa membuat kelas. Gunakan kode invite dari dosen untuk bergabung.',
                    default              => 'Akses ditolak.',
                },
                403,
                ['code' => 'DOSEN_ONLY']
            );
        }
    
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject'     => 'nullable|string|max:100',
        ]);
    
        $classroom = $this->classroomService->create($user, $data);
    
        return $this->success($classroom->load('teacher'), 'Kelas berhasil dibuat', 201);
    }
 
    // GET /classrooms/{id} — detail kelas
    public function show(Request $request, int $id)
    {
        $classroom = Classroom::with(['teacher', 'students'])->find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        if (!$classroom->isMember($request->user()->id)) {
            return $this->forbidden('Anda bukan member kelas ini');
        }
 
        return $this->success($classroom, 'Detail kelas berhasil diambil');
    }
 
    // PUT /classrooms/{id} — update kelas (guru only)
    public function update(Request $request, int $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa mengubah kelas');
        }
 
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject'     => 'nullable|string|max:100',
            'is_active'   => 'sometimes|boolean',
        ]);
 
        $classroom->update($data);
 
        return $this->success($classroom, 'Kelas berhasil diperbarui');
    }
 
    // DELETE /classrooms/{id} — hapus kelas (guru only)
    public function destroy(Request $request, int $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa menghapus kelas');
        }
 
        $classroom->delete();
 
        return $this->success(null, 'Kelas berhasil dihapus');
    }
 
    // GET /classrooms/{id}/members — list anggota
    public function members(int $id)
    {
        $classroom = Classroom::with([
            'teacher:id,name,email,avatar',
            'students:id,name,email,avatar',
        ])->find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        return $this->success([
            'teacher'  => $classroom->teacher,
            'students' => $classroom->students,
            'total'    => $classroom->students->count(),
        ], 'Daftar anggota berhasil diambil');
    }
 
    // POST /classrooms/{id}/enroll — siswa enroll manual
    public function enroll(Request $request, int $id)
    {
        $classroom = Classroom::find($id);
        $user      = $request->user();

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        if ($classroom->isTeacher($user->id)) {
            return $this->error('Guru tidak bisa enroll ke kelasnya sendiri', 400);
        }
 
        if ($classroom->isStudent($user->id)) {
            return $this->error('Kamu sudah terdaftar di kelas ini', 400);
        }
 
        $classroom->students()->attach($user->id, [
            'role'        => 'student',
            'enrolled_at' => now(),
        ]);
 
        return $this->success(null, 'Berhasil bergabung ke kelas!');
    }
 
    // DELETE /classrooms/{id}/unenroll — siswa keluar kelas
    public function unenroll(Request $request, int $id)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }

        $this->classroomService->unenroll($classroom, $request->user()->id);
 
        return $this->success(null, 'Berhasil keluar dari kelas');
    }
 
    // DELETE /classrooms/{id}/members/{userId} — guru kick siswa
    public function kickMember(Request $request, int $id, int $userId)
    {
        $classroom = Classroom::find($id);

        if (!$classroom) {
            return $this->notFound('Kelas tidak ditemukan');
        }
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa mengeluarkan anggota');
        }
 
        $this->classroomService->unenroll($classroom, $userId);
 
        return $this->success(null, 'Anggota berhasil dikeluarkan');
    }
}