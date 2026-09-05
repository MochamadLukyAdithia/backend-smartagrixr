<?php

namespace App\Services;
 
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
 
class StorageService
{
    private string $disk = 'r2';
 
    /**
     * Upload file submission siswa ke R2
     * Path: submissions/{classroom_id}/{assignment_id}/{user_id}/{filename}
     */
    public function uploadSubmission(
        UploadedFile $file,
        int $classroomId,
        int $assignmentId,
        int $userId
    ): array {
        $this->validateFile($file);
 
        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $this->generateFilename($file->getClientOriginalName());
        $path      = "submissions/{$classroomId}/{$assignmentId}/{$userId}/{$filename}";
 
        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
            ['visibility' => 'private'] // submission bersifat private
        );
 
        return [
            'path'      => $path,
            'filename'  => $filename,
            'original'  => $file->getClientOriginalName(),
            'size'      => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'url'       => $this->temporaryUrl($path), // URL sementara untuk akses
        ];
    }
 
    /**
     * Upload materi kelas (oleh guru)
     * Path: materials/{classroom_id}/{filename}
     */
    public function uploadMaterial(
        UploadedFile $file,
        int $classroomId
    ): array {
        $this->validateFile($file);
 
        $filename = $this->generateFilename($file->getClientOriginalName());
        $path     = "materials/{$classroomId}/{$filename}";
 
        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
            ['visibility' => 'private']
        );
 
        return [
            'path'      => $path,
            'filename'  => $filename,
            'original'  => $file->getClientOriginalName(),
            'size'      => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url'       => $this->temporaryUrl($path),
        ];
    }
 
    /**
     * Upload aset 3D (model GLB)
     * Path: assets/3d/{user_id}/{filename}
     */
    public function uploadAsset3D(
        UploadedFile $file,
        int $userId
    ): array {
        // Khusus GLB/GLTF
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['glb', 'gltf'])) {
            throw new \Exception('Hanya file .glb dan .gltf yang diizinkan untuk aset 3D');
        }
 
        $filename = $this->generateFilename($file->getClientOriginalName());
        $path     = "assets/3d/{$userId}/{$filename}";
 
        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
        );
 
        return [
            'path'     => $path,
            'filename' => $filename,
            'original' => $file->getClientOriginalName(),
            'size'     => $file->getSize(),
            'url'      => $this->temporaryUrl($path),
        ];
    }
 
    /**
     * Upload thumbnail untuk aset 3D
     * Path: thumbnails/{user_id}/{filename}
     */
    public function uploadThumbnail(
        UploadedFile $file,
        int $userId
    ): array {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new \Exception('Hanya file .jpg, .jpeg, .png, .webp yang diizinkan untuk thumbnail');
        }
 
        $filename = $this->generateFilename($file->getClientOriginalName());
        $path     = "thumbnails/{$userId}/{$filename}";
 
        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
        );
 
        return [
            'path'     => $path,
            'filename' => $filename,
            'original' => $file->getClientOriginalName(),
            'size'     => $file->getSize(),
            'url'      => $this->temporaryUrl($path),
        ];
    }
 
    /**
     * Generate temporary URL (berlaku 60 menit)
     * Dipakai untuk akses file private
     */
    public function temporaryUrl(string $path, int $minutes = 60): string
    {
        return Storage::disk($this->disk)
            ->temporaryUrl($path, now()->addMinutes($minutes));
    }
 
    /**
     * Hapus file dari R2
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }
 
    /**
     * Hapus semua file dalam folder
     * Contoh: hapus semua submission satu assignment
     */
    public function deleteFolder(string $folder): void
    {
        Storage::disk($this->disk)->deleteDirectory($folder);
    }
 
    /**
     * Validasi file — extension & ukuran
     */
    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed   = config('upload.allowed_extensions');
 
        // Cek extension
        if (!in_array($extension, $allowed)) {
            throw new \Exception(
                "Tipe file .{$extension} tidak diizinkan. " .
                "Tipe yang diizinkan: " . implode(', ', $allowed)
            );
        }
 
        // Cek ukuran
        $limits     = config('upload.limits');
        $maxKb      = $limits[$extension] ?? $limits['default'];
        $fileSizeKb = $file->getSize() / 1024;
 
        if ($fileSizeKb > $maxKb) {
            $maxMb = round($maxKb / 1024, 1);
            throw new \Exception(
                "Ukuran file melebihi batas. Maksimal {$maxMb}MB untuk file .{$extension}"
            );
        }
    }
 
    /**
     * Generate nama file unik untuk menghindari collision
     * Format: {timestamp}_{random}_{original_sanitized}.{ext}
     */
    private function generateFilename(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));;
        $basename  = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $basename  = Str::limit($basename, 50, ''); // max 50 char
 
        return now()->format('YmdHis') . '_' . Str::random(8) . "_{$basename}.{$extension}";
    }
}