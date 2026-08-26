<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Material, Post, Classroom};
use Illuminate\Http\Request;
 
class PostController extends Controller
{
    // GET /classrooms/{id}/feed — ambil semua post kronologis
    public function feed(Request $request, int $classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);
 
        if (!$classroom->isMember($request->user()->id)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
 
        $posts = Post::where('classroom_id', $classroomId)
            ->whereNotNull('published_at')   // hanya yang published
            ->with([
                'author:id,name,avatar',
                'assignment',
                'material',
            ])
            ->orderByDesc('is_pinned')       // pinned di atas
            ->orderByDesc('published_at')    // terbaru dulu
            ->paginate(15);
 
        return response()->json($posts);
    }
 
    // POST /classrooms/{id}/posts — guru buat post
    public function store(Request $request, int $classroomId)
    {
        $classroom = \App\Models\Classroom::findOrFail($classroomId);
    
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa posting'], 403);
        }
    
        // Normalisasi input 'files' agar selalu bertipe array (antisipasi jika client mengirim single file tanpa format files[])
        if ($request->hasFile('files') && !is_array($request->file('files'))) {
            $request->merge(['files' => [$request->file('files')]]);
        }
        
        $request->validate([
            'type'              => 'required|in:announcement,material,assignment',
            'title'             => 'required|string|max:255',
            'body'              => 'nullable|string',
            'is_pinned'         => 'boolean',
            'publish_now'       => 'boolean',
    
            // Media items (JSON string array)
            'media'             => 'nullable|array|max:10',
            'media.*.type'      => 'required_with:media|in:file,url,youtube,asset_3d,project',
            'media.*.url'       => 'required_if:media.*.type,url|required_if:media.*.type,youtube|url',
            'media.*.title'     => 'nullable|string',
            'media.*.asset_id'  => 'required_if:media.*.type,asset_3d|integer',
            'media.*.project_id'=> 'required_if:media.*.type,project|integer',
    
            // File upload (untuk tipe 'file')
            'files'             => 'nullable|array|max:10',
            'files.*'           => 'file|max:102400',
    
            // Assignment fields
            'due_at'            => 'required_if:type,assignment|nullable|date|after:now',
            'max_score'         => 'nullable|integer|min:1|max:1000',
            'allow_late'        => 'nullable|boolean',
            'category'          => 'nullable|string',
        ]);
    
        // Proses semua media
        $mediaService = app(\App\Services\MediaService::class);
        $processedMedia = [];
    
        if ($request->has('media')) {
            $processedMedia = $mediaService->processPostMedia(
                mediaItems:  $request->media,
                files:       $request->file('files') ?? [],
                classroomId: $classroomId,
                userId:      $request->user()->id,
            );
        } elseif ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $storageService = app(\App\Services\StorageService::class);
                $uploaded = $storageService->uploadMaterial($file, $classroomId);
                $processedMedia[] = [
                    'type'      => 'file',
                    'path'      => $uploaded['path'],
                    'original'  => $uploaded['original'],
                    'filename'  => $uploaded['filename'],
                    'size'      => $uploaded['size'],
                    'mime_type' => $uploaded['mime_type'],
                    'extension' => pathinfo($uploaded['original'], PATHINFO_EXTENSION),
                ];
            }
        }
    
        \DB::transaction(function () use ($request, $classroomId, $processedMedia, &$post) {
            $post = \App\Models\Post::create([
                'classroom_id' => $classroomId,
                'user_id'      => $request->user()->id,
                'type'         => $request->type,
                'title'        => $request->title,
                'body'         => $request->body,
                'media'        => $processedMedia ?: null,
                'is_pinned'    => $request->is_pinned ?? false,
                'published_at' => ($request->publish_now ?? true) ? now() : null,
            ]);
    
            if ($request->type === 'assignment') {
                \App\Models\Assignment::create([
                    'post_id'      => $post->id,
                    'classroom_id' => $classroomId,
                    'due_at'       => $request->due_at ?? null,
                    'max_score'    => $request->max_score ?? 100,
                    'allow_late'   => $request->allow_late ?? false,
                ]);
            }
    
            if ($request->type === 'material') {
                \App\Models\Material::create([
                    'post_id'      => $post->id,
                    'classroom_id' => $classroomId,
                    'category'     => $request->category ?? null,
                ]);
            }
        });
    
        return response()->json(
            $post->load(['author', 'assignment', 'material']),
            201
        );
    }
 
    // DELETE /posts/{id} — hapus post
    public function destroy(Request $request, int $postId)
    {
        $post      = Post::findOrFail($postId);
        $classroom = $post->classroom;
 
        if (!$classroom->isTeacher($request->user()->id)) {
            return response()->json(['message' => 'Hanya guru yang bisa hapus post'], 403);
        }
 
        $post->delete();
 
        return response()->json(['message' => 'Post berhasil dihapus']);
    }
}