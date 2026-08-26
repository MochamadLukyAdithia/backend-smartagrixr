<?php

namespace App\Http\Controllers;
 
use App\Models\Classroom;
use App\Services\ClassroomService;
use Illuminate\Http\Request;
 
class ClassroomController extends Controller
{
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
 
        return response()->json([
            'as_teacher' => $asTeacher,
            'as_student' => $asStudent,
        ]);
    }
 
    // POST /classrooms
    public function store(Request $request)
    {
        $user = $request->user();
    
        if (!$user->isDosen()) {
            return response()->json([
                'message' => match(true) {
                    $user->isUmum()      => 'Buat kelas hanya untuk dosen UNEJ. Login dengan akun Google UNEJ (@mail.unej.ac.id).',
                    $user->isMahasiswa() => 'Mahasiswa tidak bisa membuat kelas. Gunakan kode invite dari dosen untuk bergabung.',
                    default              => 'Akses ditolak.',
                },
                'code' => 'DOSEN_ONLY',
            ], 403);
        }
    
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject'     => 'nullable|string|max:100',
        ]);
    
        $classroom = $this->classroomService->create($user, $data);
    
        return response()->json($classroom->load('teacher'), 201);
    }
 
    // GET /classrooms/{id} — detail kelas
    public function show(Request $request, int $id)
    {
        $classroom = Classroom::with(['teacher', 'students'])->findOrFail($id);
 
        // Hanya member yang bisa lihat
        if (!$classroom->isMember($request->user()->id)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
 
        return response()->json($classroom);
    }
 
    // PUT /classrooms/{id} — update kelas (guru only)
    public function update(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa edit kelas'], 403);
        }
 
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject'     => 'nullable|string|max:100',
            'is_active'   => 'sometimes|boolean',
        ]);
 
        $classroom->update($data);
 
        return response()->json($classroom);
    }
 
    // DELETE /classrooms/{id} — hapus kelas (guru only)
    public function destroy(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa hapus kelas'], 403);
        }
 
        $classroom->delete();
 
        return response()->json(['message' => 'Kelas berhasil dihapus']);
    }
 
    // GET /classrooms/{id}/members — list anggota
    public function members(int $id)
    {
        $classroom = Classroom::with([
            'teacher:id,name,email,avatar',
            'students:id,name,email,avatar',
        ])->findOrFail($id);
 
        return response()->json([
            'teacher'  => $classroom->teacher,
            'students' => $classroom->students,
            'total'    => $classroom->students->count(),
        ]);
    }
 
    // POST /classrooms/{id}/enroll — siswa enroll manual
    public function enroll(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
        $user      = $request->user();
 
        if ($classroom->isTeacher($user->id)) {
            return response()->json(['message' => 'Guru tidak bisa enroll ke kelasnya sendiri'], 400);
        }
 
        if ($classroom->isStudent($user->id)) {
            return response()->json(['message' => 'Kamu sudah terdaftar di kelas ini'], 400);
        }
 
        $classroom->students()->attach($user->id, [
            'role'        => 'student',
            'enrolled_at' => now(),
        ]);
 
        return response()->json(['message' => 'Berhasil bergabung ke kelas!']);
    }
 
    // DELETE /classrooms/{id}/unenroll — siswa keluar kelas
    public function unenroll(Request $request, int $id)
    {
        $classroom = Classroom::findOrFail($id);
        $this->classroomService->unenroll($classroom, $request->user()->id);
 
        return response()->json(['message' => 'Berhasil keluar dari kelas']);
    }
 
    // DELETE /classrooms/{id}/members/{userId} — guru kick siswa
    public function kickMember(Request $request, int $id, int $userId)
    {
        $classroom = Classroom::findOrFail($id);
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa kick anggota'], 403);
        }
 
        $this->classroomService->unenroll($classroom, $userId);
 
        return response()->json(['message' => 'Anggota berhasil dikeluarkan']);
    }
}