<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
class LikeController extends Controller
{
    /**
     * POST /posts/{id}/like — toggle like post
     */
    public function togglePost(Request $request, int $postId)
    {
        $post = \App\Models\Post::findOrFail($postId);
        $user = $request->user();
 
        $existing = $post->likes()->where('user_id', $user->id)->first();
 
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $user->id]);
            $liked = true;
        }
 
        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }
 
    /**
     * POST /comments/{id}/like — toggle like comment
     */
    public function toggleComment(Request $request, int $commentId)
    {
        $comment  = \App\Models\Comment::findOrFail($commentId);
        $user     = $request->user();
 
        $existing = $comment->likes()->where('user_id', $user->id)->first();
 
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $comment->likes()->create(['user_id' => $user->id]);
            $liked = true;
        }
 
        return response()->json([
            'liked'       => $liked,
            'likes_count' => $comment->likes()->count(),
        ]);
    }
}