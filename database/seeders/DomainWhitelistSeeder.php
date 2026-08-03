<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\{Plan, DomainWhitelist};

class DomainWhitelistSeeder extends Seeder
{
    public function run(): void
    {
        $proPlanId = Plan::where('slug', 'pro')->value('id');
 
        $domains = [
            [
                'domain'        => 'mail.unej.ac.id',
                'instansi_name' => 'Universitas Jember',
                'plan_id'       => $proPlanId,
                'notes'         => 'Domain utama UNEJ',
            ],
            [
                'domain'        => 'unej.ac.id',
                'instansi_name' => 'Universitas Jember',
                'plan_id'       => $proPlanId,
                'notes'         => 'Domain UNEJ',
            ],
        ];
 
        foreach ($domains as $data) {
            DomainWhitelist::updateOrCreate(
                ['domain' => $data['domain']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}