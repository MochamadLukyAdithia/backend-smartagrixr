<?php

namespace App\Services;
 
use Illuminate\Http\UploadedFile;
 
class MediaService
{
    public function __construct(
        private StorageService $storageService
    ) {}
 
    /**
     * Proses semua media dari request post
     * Return array siap simpan ke DB
     */
    public function processPostMedia(
        array $mediaItems,
        array $files,
        int   $classroomId,
        int   $userId
    ): array {
        $result = [];
 
        foreach ($mediaItems as $index => $item) {
            $type = $item['type'] ?? null;
 
            $result[] = match($type) {
                'file'     => $this->processFile($files[$index] ?? null, $classroomId),
                'url'      => $this->processUrl($item),
                'youtube'  => $this->processYoutube($item),
                'asset_3d' => $this->processAsset3D($item, $userId),
                'project'  => $this->processProject($item, $userId),
                default    => throw new \Exception("Tipe media '{$type}' tidak dikenali"),
            };
        }
 
        return $result;
    }
 
    // File upload (PDF, PPT, dll)
    private function processFile(?UploadedFile $file, int $classroomId): array
    {
        if (!$file) throw new \Exception('File tidak ditemukan');
 
        $uploaded = $this->storageService->uploadMaterial($file, $classroomId);
 
        return [
            'type'      => 'file',
            'path'      => $uploaded['path'],
            'original'  => $uploaded['original'],
            'filename'  => $uploaded['filename'],
            'size'      => $uploaded['size'],
            'mime_type' => $uploaded['mime_type'],
            'extension' => pathinfo($uploaded['original'], PATHINFO_EXTENSION),
        ];
    }
 
    // Website URL
    private function processUrl(array $item): array
    {
        $url = $item['url'] ?? null;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('URL tidak valid');
        }
 
        return [
            'type'  => 'url',
            'url'   => $url,
            'title' => $item['title'] ?? $url,
        ];
    }
 
    // YouTube
    private function processYoutube(array $item): array
    {
        $url     = $item['url'] ?? null;
        $videoId = $this->extractYoutubeId($url);
 
        if (!$videoId) {
            throw new \Exception('URL YouTube tidak valid');
        }
 
        return [
            'type'      => 'youtube',
            'url'       => $url,
            'video_id'  => $videoId,
            'thumbnail' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'embed_url' => "https://www.youtube.com/embed/{$videoId}",
        ];
    }
 
    // Asset 3D (dari library aset user)
    private function processAsset3D(array $item, int $userId): array
    {
        $asset = \App\Models\Asset::where('id', $item['asset_id'])
            ->where('user_id', $userId)
            ->firstOrFail();
 
        return [
            'type'     => 'asset_3d',
            'asset_id' => $asset->id,
            'name'     => $asset->name,
            'is_pro'   => $asset->is_pro,
        ];
    }
 
    // Project Internal
    private function processProject(array $item, int $userId): array
    {
        $project = \App\Models\Project::where('id', $item['project_id'])
            ->where('user_id', $userId)
            ->firstOrFail();
 
        return [
            'type'       => 'project',
            'project_id' => $project->id,
            'name'       => $project->title,
            'status'     => $project->status,
        ];
    }
 
    // Extract YouTube Video ID
    private function extractYoutubeId(string $url): ?string
    {
        // Support berbagai format URL YouTube:
        // https://www.youtube.com/watch?v=VIDEO_ID
        // https://youtu.be/VIDEO_ID
        // https://www.youtube.com/embed/VIDEO_ID
 
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
        ];
 
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
 
        return null;
    }
}