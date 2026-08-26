<?php

namespace App\Services;

use App\Models\{Classroom, User};
use Illuminate\Support\Str;
 
class ClassroomService
{
    /**
     * Generate kode invite unik 6 karakter
     * Cek collision — generate ulang jika sudah ada
     */
    public function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        // Hapus karakter yang mirip: 0,O,1,I untuk menghindari kebingungan
 
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (Classroom::where('invite_code', $code)->exists());
 
        return $code;
    }
 
    /**
     * Buat classroom baru + generate invite code
     */
    public function create(User $teacher, array $data): Classroom
    {
        return Classroom::create([
            'teacher_id'  => $teacher->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'subject'     => $data['subject'] ?? null,
            'invite_code' => $this->generateInviteCode(),
            'is_active'   => true,
        ]);
    }
 
    /**
     * Regenerate invite code (kode lama langsung tidak berlaku)
     */
    public function regenerateInviteCode(Classroom $classroom): string
    {
        $newCode = $this->generateInviteCode();
        $classroom->update(['invite_code' => $newCode]);
        return $newCode;
    }
 
    /**
     * Enroll siswa ke kelas via kode invite
     */
    public function enrollByCode(User $student, string $code): Classroom
    {
        $classroom = Classroom::where('invite_code', strtoupper($code))
            ->where('is_active', true)
            ->firstOrFail();
 
        // Cek apakah guru mencoba masuk kelasnya sendiri
        if ($classroom->isTeacher($student->id)) {
            throw new \Exception('Guru tidak bisa enroll ke kelasnya sendiri.');
        }
 
        // Cek apakah sudah terdaftar
        if ($classroom->isStudent($student->id)) {
            throw new \Exception('Kamu sudah terdaftar di kelas ini.');
        }
 
        $classroom->students()->attach($student->id, [
            'role'        => 'student',
            'enrolled_at' => now(),
        ]);
 
        return $classroom;
    }
 
    /**
     * Unenroll siswa dari kelas
     */
    public function unenroll(Classroom $classroom, int $userId): void
    {
        $classroom->students()->detach($userId);
    }
}