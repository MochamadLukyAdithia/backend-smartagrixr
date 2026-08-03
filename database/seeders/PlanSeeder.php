<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'          => 'Free',
                'slug'          => 'free',
                'description'   => 'Untuk mencoba fitur dasar SmartAgri XR',
                'price'         => 0,
                'billing_cycle' => 'none',
                'max_assets'    => 3,
                'max_storage_mb'=> 512,
                'max_classes'   => 1,
                'features'      => json_encode([
                    'view_3d_asset',      // lihat model 3D
                    'scan_ar_marker',     // scan marker AR
                ]),
                'sort_order'    => 1,
            ],
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'description'   => 'Untuk guru & instansi — akses penuh SmartAgri XR',
                'price'         => 99000,
                'billing_cycle' => 'monthly',
                'max_assets'    => 999,
                'max_storage_mb'=> 10240, // 10GB
                'max_classes'   => 999,
                'features'      => json_encode([
                    'view_3d_asset',
                    'scan_ar_marker',
                    'upload_3d_asset',    // upload model GLB
                    'generate_qr',        // generate QR + marker
                    'create_class',       // buat kelas virtual
                    'analytics',          // lihat progres siswa
                    'custom_ar_marker',   // marker kustom
                    'ai_2d_to_3d',        // convert foto ke 3D
                ]),
                'sort_order'    => 2,
            ],
            [
                'name'          => 'Enterprise',
                'slug'          => 'enterprise',
                'description'   => 'Untuk institusi besar — unlimited segalanya',
                'price'         => 499000,
                'billing_cycle' => 'monthly',
                'max_assets'    => -1,    // unlimited
                'max_storage_mb'=> -1,
                'max_classes'   => -1,
                'features'      => json_encode([
                    'view_3d_asset',
                    'scan_ar_marker',
                    'upload_3d_asset',
                    'generate_qr',
                    'create_class',
                    'analytics',
                    'custom_ar_marker',
                    'ai_2d_to_3d',
                    'white_label',        // branding kustom
                    'api_access',         // akses REST API
                    'priority_support',   // dukungan prioritas
                ]),
                'sort_order'    => 3,
            ],
        ];
 
        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
