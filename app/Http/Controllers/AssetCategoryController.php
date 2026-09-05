<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    /**
     * GET /api/asset-categories
     * Public — dipakai FE untuk dropdown saat upload
     */
    public function index()
    {
        return response()->json(
            AssetCategory::orderBy('name')->get(['id', 'name', 'slug', 'description'])
        );
    }

    /**
     * POST /api/asset-categories
     * User bisa buat kategori baru jika tidak ada
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:asset_categories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $category = AssetCategory::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($category, 201);
    }

    /**
     * PUT /api/asset-categories/{id}
     * Admin only — update nama/deskripsi kategori
     */
    public function update(Request $request, int $id)
    {
        $category = AssetCategory::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:100|unique:asset_categories,name,' . $id,
            'description' => 'nullable|string|max:255',
        ]);

        $category->update($request->only('name', 'description'));

        return response()->json($category);
    }

    /**
     * DELETE /api/asset-categories/{id}
     * Admin only — hapus kategori (nullOnDelete di assets)
     */
    public function destroy(int $id)
    {
        $category = AssetCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
