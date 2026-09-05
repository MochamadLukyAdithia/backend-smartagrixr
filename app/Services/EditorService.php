<?php

namespace App\Services;
 
use App\Models\{Asset, ArMarker, Project};
use App\Models\User;
use Illuminate\Support\Str;
 
class EditorService
{
    public function __construct(
        private StorageService $storageService
    ) {}
 
    /**
     * Load project beserta semua data yang dibutuhkan editor
     */
    public function loadEditorData(Project $project, string $sessionId): array
    {
        $sceneData = $project->scene_data ?? [
            'scene_name'       => $project->title,
            'background_color' => '#87CEEB',
            'objects'          => [],
        ];
 
        // Resolve temporary URL untuk setiap aset di scene
        $objectsWithUrls = collect($sceneData['objects'] ?? [])
            ->map(function ($obj) {
                $asset = Asset::find($obj['asset_id']);
                if (!$asset) return null;
 
                return array_merge($obj, [
                    'file_url'       => $this->storageService->temporaryUrl($asset->file_path, 60),
                    'thumbnail_url'  => $asset->thumbnail_path
                        ? $this->storageService->temporaryUrl($asset->thumbnail_path, 60)
                        : null,
                    'asset_name'     => $asset->name,
                    'is_pro'         => $asset->is_pro,
                ]);
            })
            ->filter()
            ->values()
            ->toArray();
 
        $sceneData['objects'] = $objectsWithUrls;
 
        // Update session editor
        $project->update([
            'editor_session_id' => $sessionId,
            'last_edited_at'    => now(),
        ]);
 
        return [
            'project'    => $project->only(['id', 'title', 'status']),
            'scene_data' => $sceneData,
        ];
    }
 
    /**
     * Simpan scene data (auto-save)
     */
    public function saveScene(Project $project, array $sceneData, string $sessionId): void
    {
        // Normalisasi angka — bulatkan ke 4 desimal
        $normalizedObjects = collect($sceneData['objects'] ?? [])
            ->map(function ($obj) {
                return array_merge($obj, [
                    'position' => [
                        'x' => round($obj['position']['x'] ?? 0, 4),
                        'y' => round($obj['position']['y'] ?? 0, 4),
                        'z' => round($obj['position']['z'] ?? 0, 4),
                    ],
                    'rotation' => [
                        'x' => round($obj['rotation']['x'] ?? 0, 4),
                        'y' => round($obj['rotation']['y'] ?? 0, 4),
                        'z' => round($obj['rotation']['z'] ?? 0, 4),
                    ],
                    'scale' => [
                        'x' => round($obj['scale']['x'] ?? 1, 4),
                        'y' => round($obj['scale']['y'] ?? 1, 4),
                        'z' => round($obj['scale']['z'] ?? 1, 4),
                    ],
                ]);
            })
            ->toArray();
 
        $sceneData['objects'] = $normalizedObjects;
 
        $project->update([
            'scene_data'        => $sceneData,
            'editor_session_id' => $sessionId,
            'last_edited_at'    => now(),
        ]);
 
        // Sync aset yang dipakai
        $project->syncAssetsFromScene();
    }
 
    /**
     * Publish project
     */
    public function publishProject(Project $project, User $user): array
    {
        if ($project->hasProAssets() && !$user->isPro()) {
            throw new \Exception('Scene mengandung aset Pro. Upgrade untuk publish.');
        }

        $objects = $project->scene_data['objects'] ?? [];
        if (empty($objects)) {
            throw new \Exception('Tambahkan minimal 1 objek 3D ke scene sebelum publish.');
        }

        $arUrl = config('app.frontend_url') . '/ar/view/' . $project->id;

        $project->update(['status' => 'published']);

        return [
            'ar_url'     => $arUrl,
            'project_id' => $project->id,
        ];
    }
 
    /**
     * Generate QR Code dan simpan ke R2
     */
    private function generateQrCode(int $projectId, string $url): string
    {
        // Generate QR sebagai SVG string
        $qrSvg = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('H')
            ->generate($url);
 
        $path = "qrcodes/project_{$projectId}.svg";
 
        \Storage::disk('r2')->put($path, $qrSvg);
 
        return $path;
    }
}