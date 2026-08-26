<?php

return [
    'limits' => [
        // Dokumen
        'pdf'  => 10240,
        'ppt'  => 10240,
        'pptx' => 10240,
        'doc'  => 10240,
        'docx' => 10240,
        'xls'  => 10240,
        'xlsx' => 10240,
        'txt'  => 5120,
        // Gambar
        'jpg'  => 10240,
        'jpeg' => 10240,
        'png'  => 10240,
        'gif'  => 10240,
        'webp' => 10240,
        // Video
        'mp4'  => 10240,
        'mov'  => 10240,
        // 3D
        'glb'  => 10240,
        'gltf' => 10240,
        // Archive
        'zip'  => 10240,
        'default' => 10240,
    ],
 
    'allowed_extensions' => [
        // Dokumen
        'pdf', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'txt',
        // Gambar
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        // Video
        'mp4', 'mov',
        // 3D
        'glb', 'gltf',
        // Archive
        'zip',
    ],
 
    // Tipe media yang diizinkan di post
    'media_types' => ['file', 'url', 'youtube', 'asset_3d', 'project'],
];