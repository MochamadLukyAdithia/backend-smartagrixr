<?php

namespace App\Http\Controllers;
 
use App\Models\Project;
use App\Services\EditorService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
 
class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(private EditorService $editorService) {}
 
    /**
     * GET /api/projects
     * List semua project milik user
     */
    public function index(Request $request)
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->select(['id', 'title', 'description', 'status', 'thumbnail_path', 'updated_at'])
            ->latest()
            ->paginate(12);
 
        return $this->success($projects, 'Daftar project berhasil diambil');
    }
 
    /**
     * POST /api/projects
     * Buat project baru (kosong)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);
 
        $project = Project::create([
            'user_id'     => $request->user()->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'status'      => 'draft',
            'scene_data'  => [
                'scene_name'       => $data['title'],
                'background_color' => '#87CEEB',
                'ar_marker_id'     => null,
                'objects'          => [],
            ],
        ]);
 
        return $this->success($project, 'Project berhasil dibuat', 201);
    }
 
    /**
     * GET /api/projects/{id}/editor
     * Load data lengkap untuk editor (scene + URL aset)
     */
    public function loadEditor(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$project) {
            return $this->notFound('Project tidak ditemukan atau bukan milik Anda');
        }
 
        $sessionId = $request->header('X-Editor-Session', Str::uuid()->toString());
 
        // Cek concurrent edit
        if ($project->hasActiveEditorSession($sessionId)) {
            return response()->json([
                'success' => true,
                'message' => 'Project sedang dibuka di tab/device lain',
                'status_code' => 200,
                'data' => [
                    'session_id' => $sessionId,
                    'editor_data' => $this->editorService->loadEditorData($project, $sessionId),
                    'warning' => 'Project sedang dibuka di tab/device lain.',
                ],
            ]);
        }
 
        return $this->success([
            'session_id' => $sessionId,
            'editor_data' => $this->editorService->loadEditorData($project, $sessionId),
        ], 'Data editor berhasil dimuat');
    }
 
    /**
     * PUT /api/projects/{id}/scene
     * Auto-save scene data dari editor
     */
    public function saveScene(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$project) {
            return $this->notFound('Project tidak ditemukan atau bukan milik Anda');
        }
 
        $request->validate([
            'scene_data'                            => 'required|array',
            'scene_data.scene_name'                 => 'required|string|max:100',
            'scene_data.background_color'           => 'nullable|string|max:7',
            'scene_data.objects'                    => 'array|max:20',
            'scene_data.objects.*.uuid'             => 'required|string|max:50',
            'scene_data.objects.*.asset_id'         => 'required|integer|exists:assets,id',
            'scene_data.objects.*.position'         => 'required|array',
            'scene_data.objects.*.position.x'       => 'required|numeric|between:-100,100',
            'scene_data.objects.*.position.y'       => 'required|numeric|between:-100,100',
            'scene_data.objects.*.position.z'       => 'required|numeric|between:-100,100',
            'scene_data.objects.*.rotation'         => 'required|array',
            'scene_data.objects.*.rotation.x'       => 'required|numeric|between:-360,360',
            'scene_data.objects.*.rotation.y'       => 'required|numeric|between:-360,360',
            'scene_data.objects.*.rotation.z'       => 'required|numeric|between:-360,360',
            'scene_data.objects.*.scale'            => 'required|array',
            'scene_data.objects.*.scale.x'          => 'required|numeric|min:0.001|max:100',
            'scene_data.objects.*.scale.y'          => 'required|numeric|min:0.001|max:100',
            'scene_data.objects.*.scale.z'          => 'required|numeric|min:0.001|max:100',
            'scene_data.objects.*.label'            => 'nullable|string|max:100',
            'scene_data.objects.*.description'      => 'nullable|string|max:500',
            'scene_data.objects.*.label_color'      => 'nullable|string|max:7',
            'scene_data.objects.*.animation'        => 'nullable|in:none,rotate,float,pulse',
            'scene_data.objects.*.visible'          => 'nullable|boolean',
            'session_id'                            => 'nullable|string',
        ]);
 
        $sessionId = $request->input('session_id', Str::uuid()->toString());
 
        $this->editorService->saveScene(
            project:   $project,
            sceneData: $request->scene_data,
            sessionId: $sessionId,
        );
 
        return $this->success([
            'session_id' => $sessionId,
            'saved_at' => now()->toISOString(),
        ], 'Scene berhasil tersimpan');
    }
 
    /**
     * PUT /api/projects/{id}/publish
     * Publish project → generate QR Code
     */
    public function publish(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$project) {
            return $this->notFound('Project tidak ditemukan atau bukan milik Anda');
        }
 
        try {
            $result = $this->editorService->publishProject($project, $request->user());
 
            return $this->success($result, 'Project berhasil dipublish!', 200);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
 
    /**
     * PUT /api/projects/{id}/unpublish
     * Tarik project kembali ke draft
     */
    public function unpublish(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$project) {
            return $this->notFound('Project tidak ditemukan atau bukan milik Anda');
        }
 
        $project->update(['status' => 'draft']);
 
        return $this->success($project, 'Project dikembalikan ke draft');
    }
 
    /**
     * DELETE /api/projects/{id}
     * Hapus project
     */
    public function destroy(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$project) {
            return $this->notFound('Project tidak ditemukan atau bukan milik Anda');
        }
 
        $project->delete();
 
        return $this->success(null, 'Project berhasil dihapus');
    }
}