<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/asset-categories
     * Public — dipakai FE untuk dropdown saat upload
     */
    public function index()
    {
        $categories = AssetCategory::orderBy('name')->get(['id', 'name', 'slug', 'description']);
        return $this->success($categories, 'Kategori aset berhasil diambil');
    }

    /**
     * POST /api/asset-categories
     * User bisa buat kategori baru jika tidak ada
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:asset_categories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $category = AssetCategory::create($validated);

        return $this->success($category, 'Kategori berhasil dibuat', 201);
    }

    /**
     * PUT /api/asset-categories/{id}
     * Admin only — update nama/deskripsi kategori
     */
    public function update(Request $request, int $id)
    {
        $category = AssetCategory::find($id);

        if (!$category) {
            return $this->notFound('Kategori tidak ditemukan');
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:asset_categories,name,' . $id,
            'description' => 'nullable|string|max:255',
        ]);

        $category->update($validated);

        return $this->success($category, 'Kategori berhasil diperbarui');
    }

    /**
     * DELETE /api/asset-categories/{id}
     * Admin only — hapus kategori (nullOnDelete di assets)
     */
    public function destroy(int $id)
    {
        $category = AssetCategory::find($id);

        if (!$category) {
            return $this->notFound('Kategori tidak ditemukan');
        }

        $category->delete();

        return $this->success(null, 'Kategori berhasil dihapus');
    }
}

