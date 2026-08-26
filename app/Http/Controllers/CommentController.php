<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
class CommentController extends Controller
{
    /**
     * GET /posts/{id}/comments — ambil semua comment + replies
     */
    public function index(Request $request, int $postId)
    {
        $post    = \App\Models\Post::findOrFail($postId);
        $userId  = $request->user()->id;
 
        $comments = $post->comments()
            ->paginate(20);
 
        // Tambah info apakah user sudah like
        $comments->getCollection()->transform(function ($comment) use ($userId) {
            $comment->is_liked    = $comment->likes->where('user_id', $userId)->isNotEmpty();
            $comment->likes_count = $comment->likes->count();
            unset($comment->likes);
 
            $comment->replies->transform(function ($reply) use ($userId) {
                $reply->is_liked    = $reply->likes->where('user_id', $userId)->isNotEmpty();
                $reply->likes_count = $reply->likes->count();
                unset($reply->likes);
                return $reply;
            });
 
            return $comment;
        });
 
        return response()->json($comments);
    }
 
    /**
     * POST /posts/{id}/comments — buat comment baru
     */
    public function store(Request $request, int $postId)
    {
        $post = \App\Models\Post::with('classroom')->findOrFail($postId);
 
        // Hanya member kelas yang bisa comment
        if (!$post->classroom->isMember($request->user()->id)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
 
        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);
 
        $comment = \App\Models\Comment::create([
            'user_id'   => $request->user()->id,
            'post_id'   => $postId,
            'parent_id' => null, // comment biasa
            'body'      => $data['body'],
        ]);
 
        return response()->json(
            $comment->load('user:id,name,avatar'),
            201
        );
    }
 
    /**
     * POST /comments/{id}/reply — reply ke comment
     */
    public function reply(Request $request, int $commentId)
    {
        $parent = \App\Models\Comment::with('post.classroom')->findOrFail($commentId);
 
        // Cegah reply ke reply (hanya 1 level)
        if ($parent->isReply()) {
            return response()->json(['message' => 'Tidak bisa reply ke reply'], 400);
        }
 
        if (!$parent->post->classroom->isMember($request->user()->id)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
 
        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);
 
        $reply = \App\Models\Comment::create([
            'user_id'   => $request->user()->id,
            'post_id'   => $parent->post_id,
            'parent_id' => $commentId, // ← ini reply
            'body'      => $data['body'],
        ]);
 
        return response()->json(
            $reply->load('user:id,name,avatar'),
            201
        );
    }
 
    /**
     * DELETE /comments/{id} — hapus comment
     */
    public function destroy(Request $request, int $commentId)
    {
        $comment = \App\Models\Comment::findOrFail($commentId);
 
        // Hanya penulis atau guru yang bisa hapus
        $isAuthor  = $comment->user_id === $request->user()->id;
        $isTeacher = $comment->post->classroom->isTeacher($request->user()->id);
 
        if (!$isAuthor && !$isTeacher) {
            return response()->json(['message' => 'Tidak bisa menghapus comment ini'], 403);
        }
 
        $comment->delete();
 
        return response()->json(['message' => 'Comment dihapus']);
    }
}