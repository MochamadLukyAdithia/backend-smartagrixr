<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{GradeLevel, Subject};
 
class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Literasi',             'slug' => 'literasi', 'sort_order' => 1],
            ['name' => 'Sains',                'slug' => 'sains', 'sort_order' => 2],
            ['name' => 'Matematika',           'slug' => 'matematika', 'sort_order' => 3],
            ['name' => 'Pendidikan Pancasila', 'slug' => 'pendidikan-pancasila', 'sort_order' => 4],
            ['name' => 'Umum',                 'slug' => 'umum', 'sort_order' => 5],
            ['name' => 'Agroteknologi',        'slug' => 'agroteknologi', 'sort_order' => 6],
            ['name' => 'Biologi',              'slug' => 'biologi', 'sort_order' => 7],
        ];
 
        foreach ($subjects as $data) {
            Subject::updateOrCreate(['slug' => $data['slug']], array_merge($data, ['is_active' => true]));
        }
 
        $gradeLevels = [
            ['name' => 'Prasekolah', 'slug' => 'prasekolah', 'level_type' => 'prasekolah', 'sort_order' => 1],
            ['name' => 'TK A',       'slug' => 'tk-a', 'level_type' => 'tk',         'sort_order' => 2],
            ['name' => 'TK B',       'slug' => 'tk-b', 'level_type' => 'tk',         'sort_order' => 3],
            ['name' => 'SD',         'slug' => 'sd', 'level_type' => 'sd',         'sort_order' => 4],
            ['name' => 'Kelas 1',    'slug' => 'kelas-1', 'level_type' => 'sd',         'sort_order' => 5],
            ['name' => 'Kelas 2',    'slug' => 'kelas-2', 'level_type' => 'sd',         'sort_order' => 6],
            ['name' => 'Kelas 3',    'slug' => 'kelas-3', 'level_type' => 'sd',         'sort_order' => 7],
            ['name' => 'Kelas 4',    'slug' => 'kelas-4', 'level_type' => 'sd',         'sort_order' => 8],
            ['name' => 'Kelas 5',    'slug' => 'kelas-5', 'level_type' => 'sd',         'sort_order' => 9],
            ['name' => 'Kelas 6',    'slug' => 'kelas-6', 'level_type' => 'sd',         'sort_order' => 10],
            ['name' => 'Kelas 7',    'slug' => 'kelas-7', 'level_type' => 'smp',        'sort_order' => 11],
            ['name' => 'Kelas 8',    'slug' => 'kelas-8', 'level_type' => 'smp',        'sort_order' => 12],
            ['name' => 'Kelas 9',    'slug' => 'kelas-9', 'level_type' => 'smp',        'sort_order' => 13],
            ['name' => 'Kelas 10',   'slug' => 'kelas-10', 'level_type' => 'sma',        'sort_order' => 14],
            ['name' => 'Kelas 11',   'slug' => 'kelas-11', 'level_type' => 'sma',        'sort_order' => 15],
            ['name' => 'Kelas 12',   'slug' => 'kelas-12', 'level_type' => 'sma',        'sort_order' => 16],
        ];
 
        foreach ($gradeLevels as $data) {
            GradeLevel::updateOrCreate(['slug' => $data['slug']], array_merge($data, ['is_active' => true]));
        }
    }
}