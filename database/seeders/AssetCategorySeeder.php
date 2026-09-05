<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetCategory;

class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tanaman',       'slug' => 'tanaman',       'description' => 'Model 3D tanaman pertanian'],
            ['name' => 'Mesin',         'slug' => 'mesin',         'description' => 'Model 3D alat dan mesin pertanian'],
            ['name' => 'Hama',          'slug' => 'hama',          'description' => 'Model 3D hama dan penyakit tanaman'],
            ['name' => 'Ekosistem',     'slug' => 'ekosistem',     'description' => 'Model 3D ekosistem dan lingkungan'],
            ['name' => 'Infrastruktur', 'slug' => 'infrastruktur', 'description' => 'Model 3D infrastruktur pertanian'],
            ['name' => 'Lainnya',       'slug' => 'lainnya',       'description' => 'Kategori umum untuk aset lainnya'],
        ];

        foreach ($categories as $cat) {
            AssetCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
