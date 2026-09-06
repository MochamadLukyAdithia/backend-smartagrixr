<?php

namespace App\Http\Controllers;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
 
class AssetController extends Controller
{
    use ApiResponse;

    public function __construct(private StorageService $storageService) {}
 
    /**
     * GET /api/assets
     * List aset: milik user + library publik SmartAgri
     * Dipakai untuk sidebar editor
     */
    public function index(Request $request)
    {
        $user = $request->user();
 
        // Aset milik user sendiri
        $myAssets = Asset::where('user_id', $user->id)
            ->where('type', 'glb')
            ->select(['id', 'name', 'category_id', 'thumbnail_path', 'is_pro', 'is_public', 'file_size'])
            ->latest()
            ->get();
 
        // Library publik SmartAgri (bukan milik user)
        $publicAssets = Asset::where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->where('type', 'glb')
            ->select(['id', 'name', 'category_id', 'thumbnail_path', 'is_pro', 'is_public', 'file_size'])
            ->get();
 
        // Gabungkan & resolve thumbnail URL
        $resolve = fn($assets) => $assets->map(fn($asset) => [
            'id'            => $asset->id,
            'name'          => $asset->name,
            'category'      => $asset->category?->name,
            'category_id'   => $asset->category_id,
            'is_pro'        => $asset->is_pro,
            'is_public'     => $asset->is_public,
            'file_size'     => $asset->file_size,
            'thumbnail_url' => $asset->thumbnail_path
                ? $this->storageService->temporaryUrl($asset->thumbnail_path, 120)
                : null,
        ]);
 
        return $this->success([
            'my_assets'     => $resolve($myAssets),
            'public_assets' => $resolve($publicAssets),
        ], 'Daftar aset berhasil diambil');
    }
 
    /**
     * GET /api/assets/{id}/url
     * Generate temporary URL untuk download/load GLB
     * Dipanggil saat user drag aset ke canvas
     */
    public function getUrl(Request $request, int $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return $this->notFound('Aset tidak ditemukan');
        }
 
        // Cek akses: milik user, atau publik, atau user Pro (untuk aset Pro)
        $canAccess = $asset->user_id === $request->user()->id
            || $asset->is_public
            || ($asset->is_pro && $request->user()->isPro());
 
        if (!$canAccess) {
            return $this->error('Aset Pro memerlukan akun Pro.', 403, ['upgrade_url' => '/pricing']);
        }
 
        return $this->success([
            'url'     => $this->storageService->temporaryUrl($asset->file_path, 30),
            'expires' => now()->addMinutes(30)->toISOString(),
        ], 'URL aset berhasil dibuat');
    }
 
    /**
     * POST /api/assets/upload
     * Upload GLB baru (Pro only)
     */
    public function upload(Request $request)
    {
        // Cek feature flag
        if (!$request->user()->hasFeature('upload_3d_asset')) {
            return $this->error('Upload aset 3D memerlukan akun Pro.', 403, ['upgrade_url' => '/pricing']);
        }

        $user = $request->user();
        $plan = $user->activeSubscription?->plan;

        if ($plan) {
            // Cek kuota jumlah aset
            $currentAssetCount = Asset::where('user_id', $user->id)->count();
            if ($plan->max_assets !== -1 && $currentAssetCount >= $plan->max_assets) {
                return $this->error(
                    "Kuota aset plan {$plan->name} sudah penuh ({$plan->max_assets} aset). Upgrade untuk menambah kuota.",
                    403,
                    ['upgrade_url' => '/pricing']
                );
            }

            // Cek kuota storage
            $currentStorageMb = Asset::where('user_id', $user->id)->sum('file_size') / 1024 / 1024;
            $incomingFileMb   = $request->file('file')->getSize() / 1024 / 1024;

            if ($plan->max_storage_mb !== -1 && ($currentStorageMb + $incomingFileMb) > $plan->max_storage_mb) {
                return $this->error(
                    "Kuota storage plan {$plan->name} tidak cukup. Sisa: " . round($plan->max_storage_mb - $currentStorageMb, 2) . " MB.",
                    403,
                    ['upgrade_url' => '/pricing']
                );
            }
        }
 
        $request->validate([
            'file'        => 'required|file|max:'. config('services.upload.max_file_size_mb', 10240),
            'thumbnail'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'name'        => 'required|string|max:100',
            'category_id' => 'nullable|exists:asset_categories,id',
            'category_name' => 'nullable|string|max:100',
            'is_public'   => 'nullable|boolean',
        ]);
 
        $file = $request->file('file');
 
        // Validasi ekstensi
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['glb', 'gltf'])) {
            return $this->error('Hanya file .glb dan .gltf yang diizinkan', 422);
        }
 
        // Upload ke R2
        $uploaded = $this->storageService->uploadAsset3D($file, $request->user()->id);
 
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $uploaded_thumb = $this->storageService->uploadThumbnail($thumbnail, $request->user()->id);
            $thumbnailPath = $uploaded_thumb['path'];
        }

        // Simpan ke DB
        // Handle category: gunakan category_id atau buat baru jika category_name diberikan
        $categoryId = $request->category_id;
        if (!$categoryId && $request->category_name) {
            $category = AssetCategory::firstOrCreate(
                ['name' => $request->category_name],
                ['slug' => \Illuminate\Support\Str::slug($request->category_name)]
            );
            $categoryId = $category->id;
        }
 
        
        try {
            $asset = Asset::create([
                'user_id'        => $request->user()->id,
                'name'           => $request->name,
                'original_name'  => $file->getClientOriginalName(),
                'file_path'      => $uploaded['path'],
                'thumbnail_path' => $thumbnailPath,
                'type'           => 'glb',
                'category_id'    => $categoryId,
                'is_pro'         => false,
                'is_public'      => $request->boolean('is_public', false),
                'file_size'      => $file->getSize(),
            ]);

            return $this->success([
                'id'       => $asset->id,
                'name'     => $asset->name,
                'category' => $asset->category,
                'file_url' => $this->storageService->temporaryUrl($uploaded['path'], 30),
            ], 'Aset berhasil diupload', 201);

        } catch (\Throwable $e) {
            // Membersihkan file yang terupload
            $this->storageService->delete($uploaded['path']);

            if (isset($thumbnailPath)) {
                $this->storageService->delete($thumbnailPath);
            }

            \Log::error('Gagal menyimpan asset setelah upload ke R2', ['error' => $e->getMessage()]);

            return $this->error('Gagal menyimpan aset. Coba lagi.', 500);
        }
 
 
    }
 
    /**
     * DELETE /api/assets/{id}
     * Hapus aset
     */
    public function destroy(Request $request, int $id)
    {
        $asset = Asset::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$asset) {
            return $this->notFound('Aset tidak ditemukan atau bukan milik Anda');
        }
 
        // Cek apakah masih dipakai di project lain
        $usedInProjects = $asset->projects()->count();
        if ($usedInProjects > 0) {
            return $this->error(
                "Aset masih digunakan di {$usedInProjects} project. Hapus dari project dulu.",
                422
            );
        }
 
        // Hapus file dari R2
        $this->storageService->delete($asset->file_path);
        if ($asset->thumbnail_path) {
            $this->storageService->delete($asset->thumbnail_path);
        }
 
        $asset->delete();
 
        return $this->success(null, 'Aset berhasil dihapus');
    }
}