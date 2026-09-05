<?php

namespace App\Http\Controllers;
 
use App\Models\Project;
use App\Services\EditorService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
 
class ProjectController extends Controller
{
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
 
        return response()->json($projects);
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
 
        return response()->json($project, 201);
    }
 
    /**
     * GET /api/projects/{id}/editor
     * Load data lengkap untuk editor (scene + URL aset)
     */
    public function loadEditor(Request $request, int $id)
    {
        $project   = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
 
        $sessionId = $request->header('X-Editor-Session', Str::uuid()->toString());
 
        // Cek concurrent edit
        if ($project->hasActiveEditorSession($sessionId)) {
            return response()->json([
                'warning'    => 'Project sedang dibuka di tab/device lain.',
                'session_id' => $sessionId,
                'data'       => $this->editorService->loadEditorData($project, $sessionId),
            ]);
        }
 
        return response()->json([
            'session_id' => $sessionId,
            'data'       => $this->editorService->loadEditorData($project, $sessionId),
        ]);
    }
 
    /**
     * PUT /api/projects/{id}/scene
     * Auto-save scene data dari editor
     */
    public function saveScene(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
 
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
 
        return response()->json([
            'message'  => 'Scene tersimpan',
            'saved_at' => now()->toISOString(),
        ]);
    }
 
    /**
     * PUT /api/projects/{id}/publish
     * Publish project → generate QR Code
     */
    public function publish(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
 
        try {
            $result = $this->editorService->publishProject($project, $request->user());
 
            return response()->json([
                'message' => 'Project berhasil dipublish!',
                ...$result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
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
            ->firstOrFail();
 
        $project->update(['status' => 'draft']);
 
        return response()->json(['message' => 'Project dikembalikan ke draft']);
    }
 
    /**
     * DELETE /api/projects/{id}
     * Hapus project
     */
    public function destroy(Request $request, int $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
 
        $project->delete();
 
        return response()->json(['message' => 'Project dihapus']);
    }
}