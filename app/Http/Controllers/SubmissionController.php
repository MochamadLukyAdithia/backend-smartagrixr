<?php

namespace App\Http\Controllers;

use App\Services\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
 
class SubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private StorageService $storageService
    ) {}
 
    /**
     * POST /assignments/{id}/submit
     * Siswa kumpul tugas + upload file
     */
    public function submit(Request $request, int $assignmentId)
    {
        $assignment = \App\Models\Assignment::with('post.classroom')->find($assignmentId);
        $user       = $request->user();

        if (!$assignment) {
            return $this->notFound('Assignment tidak ditemukan');
        }
 
        if (!$assignment->post->classroom->isStudent($user->id)) {
            return $this->forbidden('Kamu bukan anggota kelas ini');
        }
 
        if ($assignment->isExpired() && !$assignment->allow_late) {
            return $this->error('Deadline sudah lewat', 400);
        }
 
        $request->validate([
            'content'       => 'nullable|string|max:5000',
            'files'         => 'nullable|array|max:5',          // max 5 file
            'files.*'       => 'file|max:102400',               // max 100MB per file (validasi awal)
        ]);
 
        // Upload semua file ke R2
        $attachments = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try {
                    $uploaded      = $this->storageService->uploadSubmission(
                        file:         $file,
                        classroomId:  $assignment->post->classroom->id,
                        assignmentId: $assignmentId,
                        userId:       $user->id,
                    );
                    $attachments[] = $uploaded;
                } catch (\Exception $e) {
                    // Rollback file yang sudah terupload jika ada error
                    foreach ($attachments as $uploaded) {
                        $this->storageService->delete($uploaded['path']);
                    }
                    return $this->error($e->getMessage(), 422);
                }
            }
        }
 
        // Simpan submission (tanpa URL — URL di-generate saat diakses)
        $attachmentsForDb = array_map(fn($a) => [
            'path'      => $a['path'],
            'filename'  => $a['filename'],
            'original'  => $a['original'],
            'size'      => $a['size'],
            'mime_type' => $a['mime_type'],
            'extension' => $a['extension'],
            // ❌ Tidak simpan URL — URL expired, generate saat diakses
        ], $attachments);
 
        $submission = \App\Models\Submission::updateOrCreate(
            ['assignment_id' => $assignmentId, 'user_id' => $user->id],
            [
                'content'      => $request->content ?? null,
                'attachments'  => $attachmentsForDb,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]
        );
 
        // Return dengan temporary URL
        return $this->success([
            'submission'  => $submission,
            'attachments' => $this->resolveUrls($submission->attachments ?? []),
        ], 'Tugas berhasil dikumpulkan', 201);
    }
 
    /**
     * GET /submissions/{id}/download/{index}
     * Generate temporary URL untuk download file
     */
    public function download(Request $request, int $submissionId, int $fileIndex)
    {
        $submission = \App\Models\Submission::with('assignment.post.classroom')->findOrFail($submissionId);
        $user       = $request->user();
        $classroom  = $submission->assignment->post->classroom;
 
        // Hanya siswa pemilik atau guru yang bisa download
        $canAccess = $submission->user_id === $user->id
            || $classroom->isTeacher($user->id);
 
        if (!$canAccess) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
 
        $attachments = $submission->attachments ?? [];
 
        if (!isset($attachments[$fileIndex])) {
            return $this->notFound('File tidak ditemukan');
        }
 
        $file = $attachments[$fileIndex];
 
        // Generate temporary URL berlaku 15 menit
        $url = $this->storageService->temporaryUrl($file['path'], 15);
 
        return $this->success([
            'url'      => $url,
            'filename' => $file['original'],
            'expires'  => now()->addMinutes(15)->toISOString(),
        ], 'URL download berhasil dibuat');
    }
 
    /**
     * GET /assignments/{id}/submissions
     * Guru lihat semua submission
     */
    public function index(Request $request, int $assignmentId)
    {
        $assignment = \App\Models\Assignment::with('post.classroom')->find($assignmentId);

        if (!$assignment) {
            return $this->notFound('Assignment tidak ditemukan');
        }
 
        if (!$assignment->post->classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa melihat semua submission');
        }
 
        $submissions = $assignment->submissions()
            ->with('student:id,name,email,avatar')
            ->latest()
            ->get()
            ->map(function ($sub) {
                // Resolve URL untuk setiap attachment
                $sub->attachments = $this->resolveUrls($sub->attachments ?? []);
                return $sub;
            });
 
        return $this->success([
            'total'     => $submissions->count(),
            'submitted' => $submissions->where('status', 'submitted')->count(),
            'graded'    => $submissions->where('status', 'graded')->count(),
            'data'      => $submissions,
        ], 'Daftar submission berhasil diambil');
    }
 
    /**
     * POST /submissions/{id}/grade
     * Guru beri nilai
     */
    public function grade(Request $request, int $submissionId)
    {
        $submission = \App\Models\Submission::with('assignment.post.classroom')
            ->find($submissionId);

        if (!$submission) {
            return $this->notFound('Submission tidak ditemukan');
        }
 
        if (!$submission->assignment->post->classroom->isTeacher($request->user()->id)) {
            return $this->forbidden('Hanya guru yang bisa memberi nilai');
        }
 
        $data = $request->validate([
            'score'    => "required|integer|min:0|max:{$submission->assignment->max_score}",
            'feedback' => 'nullable|string',
        ]);
 
        $submission->update([
            'score'    => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status'   => 'graded',
        ]);
 
        return $this->success($submission, 'Nilai berhasil diberikan');
    }
 
    /**
     * Helper: tambahkan temporary URL ke setiap attachment
     */
    private function resolveUrls(array $attachments): array
    {
        return array_map(function ($attachment) {
            $attachment['url']     = $this->storageService->temporaryUrl($attachment['path'], 60);
            $attachment['expires'] = now()->addMinutes(60)->toISOString();
            return $attachment;
        }, $attachments);
    }
}