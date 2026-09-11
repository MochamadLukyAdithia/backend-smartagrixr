<?php

namespace App\Http\Controllers;
 
use App\Models\{GradeLevel, LearningContent, Subject};
use App\Services\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
 
class LearningContentController extends Controller
{
    use ApiResponse;

    public function __construct(private StorageService $storageService) {}
 
    /**
     * GET /api/learn
     * Homepage: list subjects + grade_levels + konten terbaru
     */
    public function home()
    {
        $subjects = Subject::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['contents' => fn($q) => $q->where('is_published', true)])
            ->get(['id', 'name', 'slug']);
 
        $gradeLevels = GradeLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'level_type']);
 
        // Konten terbaru (6 item untuk homepage)
        $latest = LearningContent::where('is_published', true)
            ->with([
                'subject:id,name,slug',
                'gradeLevel:id,name,slug',
            ])
            ->select(['id', 'title', 'subject_id', 'grade_level_id', 'thumbnail_path', 'embed_url', 'created_at'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn($c) => $this->formatContent($c));
 
        return $this->success([
            'subjects'     => $subjects,
            'grade_levels' => $gradeLevels,
            'latest'       => $latest,
        ], 'Home data berhasil diambil');
    }
 
    /**
     * GET /api/learn/subjects
     * List semua mata pelajaran
     */
    public function subjects()
    {
        $subjects = Subject::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['contents' => fn($q) => $q->where('is_published', true)])
            ->get(['id', 'name', 'slug']);
 
        return $this->success(['data' => $subjects], 'Daftar mata pelajaran berhasil diambil');
    }
 
    /**
     * GET /api/learn/grades
     * List semua tingkat kelas
     */
    public function grades()
    {
        $grades = GradeLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'level_type']);
 
        return $this->success(['data' => $grades], 'Daftar tingkat kelas berhasil diambil');
    }
 
    /**
     * GET /api/learn/contents
     * List konten dengan filter + paginasi
     *
     * Query params:
     * ?subject=sains        → filter by subject slug
     * ?grade=kelas-11       → filter by grade slug
     * ?q=hidrokarbon        → search by title
     * ?sort=latest|oldest   → urutan
     * ?per_page=12          → item per halaman (default 12)
     */
    public function index(Request $request)
    {
        $perPage = min((int) ($request->per_page ?? 12), 48); // max 48 per page
 
        $query = LearningContent::where('is_published', true)
            ->with([
                'subject:id,name,slug',
                'gradeLevel:id,name,slug',
            ])
            ->select([
                'id', 'title', 'subject_id', 'grade_level_id',
                'thumbnail_path', 'embed_url', 'created_at',
            ]);
 
        // Filter by subject slug
        if ($request->filled('subject')) {
            $query->whereHas('subject', fn($q) =>
                $q->where('slug', $request->subject)
            );
        }
 
        // Filter by grade level slug
        if ($request->filled('grade')) {
            $query->whereHas('gradeLevel', fn($q) =>
                $q->where('slug', $request->grade)
            );
        }
 
        // Search by title
        if ($request->filled('q')) {
            $query->where('title', 'ilike', "%{$request->q}%");
            // pakai 'like' jika database bukan PostgreSQL
        }
 
        // Sort
        match($request->sort ?? 'latest') {
            'oldest' => $query->oldest(),
            default  => $query->latest(),
        };
 
        $contents = $query->paginate($perPage);
 
        // Transform setiap item
        $contents->getCollection()->transform(
            fn($c) => $this->formatContent($c)
        );
 
        return $this->success($contents, 'Daftar konten berhasil diambil');
    }
 
    /**
     * GET /api/learn/contents/{id}
     * Detail satu konten + konten terkait (paginasi)
     *
     * Query params:
     * ?related_page=1      → halaman konten terkait
     * ?related_per_page=4  → item terkait per halaman
     */
    public function show(Request $request, int $id)
    {
        $content = LearningContent::where('id', $id)
            ->where('is_published', true)
            ->with([
                'subject:id,name,slug',
                'gradeLevel:id,name,slug',
                'user:id,name,avatar',
            ])
            ->first();

        if (!$content) {
            return $this->notFound('Konten tidak ditemukan');
        }
 
        $thumbnailUrl = $content->thumbnail_path
            ? $this->storageService->temporaryUrl($content->thumbnail_path, 60)
            : null;
 
        // Konten terkait — by subject yang sama, paginasi
        $relatedPerPage = min((int) ($request->related_per_page ?? 4), 12);
 
        $related = LearningContent::where('is_published', true)
            ->where('id', '!=', $content->id)
            ->where('subject_id', $content->subject_id)
            ->with(['subject:id,name,slug', 'gradeLevel:id,name,slug'])
            ->select(['id', 'title', 'subject_id', 'grade_level_id', 'thumbnail_path', 'embed_url', 'created_at'])
            ->latest()
            ->paginate($relatedPerPage, ['*'], 'related_page');
 
        $related->getCollection()->transform(
            fn($c) => $this->formatContent($c)
        );
 
        return $this->success([
            'id'            => $content->id,
            'title'         => $content->title,
            'description'   => $content->description,
            'content_type'  => $content->content_type,
            'embed_url'     => $content->embed_ready_url,
            'thumbnail_url' => $thumbnailUrl,
            'subject'       => $content->subject->only(['id', 'name', 'slug']),
            'grade_level'   => $content->gradeLevel->only(['id', 'name', 'slug']),
            'author'        => $content->user->only(['id', 'name', 'avatar']),
            'created_at'    => $content->created_at,
            'related'       => $related, // paginated
        ], 'Konten berhasil diambil');
    }
 
    /**
     * POST /api/learn/contents
     * Buat konten baru — hanya dosen/admin
     */
    public function store(Request $request)
    {
        $user = $request->user();
 
        if (!$user->isDosen() && !$user->isAdmin()) {
            return $this->forbidden('Hanya dosen yang bisa membuat konten');
        }
 
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'subject_id'     => 'required|exists:subjects,id',
            'grade_level_id' => 'required|exists:grade_levels,id',
            'embed_url'      => 'required|url',
            'thumbnail'      => 'nullable|image|max:2048',
            'is_published'   => 'boolean',
        ]);
 
        // Upload thumbnail jika ada
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $uploaded      = $this->storageService->uploadThumbnail($request->file('thumbnail'), $user->id);
            $thumbnailPath = $uploaded['path'];
        }
 
        $content = LearningContent::create([
            'user_id'        => $user->id,
            'subject_id'     => $data['subject_id'],
            'grade_level_id' => $data['grade_level_id'],
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'embed_url'      => $data['embed_url'],
            'thumbnail_path' => $thumbnailPath,
            'is_published'   => $data['is_published'] ?? false,
        ]);
 
        return $this->success(
            $content->load(['subject', 'gradeLevel']),
            'Konten berhasil dibuat',
            201
        );
    }
 
    /**
     * PUT /api/learn/contents/{id}
     * Update konten — hanya pemilik atau admin
     */
    public function update(Request $request, int $id)
    {
        $content = LearningContent::find($id);
        
        if (!$content) {
            return $this->notFound('Konten tidak ditemukan');
        }
        
        $user = $request->user();
 
        if ($content->user_id !== $user->id && !$user->isAdmin()) {
            return $this->forbidden('Tidak bisa mengedit konten ini');
        }
 
        $data = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'subject_id'     => 'sometimes|exists:subjects,id',
            'grade_level_id' => 'sometimes|exists:grade_levels,id',
            'embed_url'      => 'sometimes|url',
            'is_published'   => 'boolean',
        ]);
 
        // Update thumbnail jika ada file baru
        if ($request->hasFile('thumbnail')) {
            if ($content->thumbnail_path) {
                $this->storageService->delete($content->thumbnail_path);
            }
            $uploaded             = $this->storageService->uploadThumbnail($request->file('thumbnail'), $user->id);
            $data['thumbnail_path'] = $uploaded['path'];
        }
 
        $content->update($data);
 
        return $this->success(
            $content->load(['subject', 'gradeLevel']),
            'Konten berhasil diupdate'
        );
    }
 
    /**
     * DELETE /api/learn/contents/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $content = LearningContent::find($id);
        
        if (!$content) {
            return $this->notFound('Konten tidak ditemukan');
        }
        
        $user = $request->user();

        if ($content->user_id !== $user->id && !$user->isAdmin()) {
            return $this->forbidden('Tidak bisa menghapus konten ini');
        }
 
        if ($content->thumbnail_path) {
            $this->storageService->delete($content->thumbnail_path);
        }
 
        $content->delete();

        return $this->success(null, 'Konten berhasil dihapus');
    }

    private function formatContent(LearningContent $content): array
    {
        return [
            'id'            => $content->id,
            'title'         => $content->title,
            'content_type'  => $content->content_type,
            'subject'       => $content->subject?->only(['id', 'name', 'slug']),
            'grade_level'   => $content->gradeLevel?->only(['id', 'name', 'slug']),
            'embed_url'     => $content->embed_url,
            'thumbnail_url' => $content->thumbnail_path
                ? $this->storageService->temporaryUrl($content->thumbnail_path, 120)
                : null,
            'created_at'    => $content->created_at,
        ];
    }
}