<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PostCommentController extends Controller
{
    /**
     * Display a listing of comments for a specific post.
     */
    public function index($postId)
    {
        // Verify post exists
        $post = Post::findOrFail($postId);
        
        $comments = PostComment::with('user:id,name,profile_photo')
                             ->where('post_id', $postId)
                             ->orderBy('created_at', 'desc')
                             ->paginate(20);
        
        return response()->json([
            'status' => true,
            'message' => 'Comments retrieved successfully',
            'data' => $comments
        ], 200);
    }

    /**
     * Store a newly created comment in storage.
     */
    public function store(Request $request, $postId)
    {
        // Verify post exists
        $post = Post::findOrFail($postId);
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $commentData = $validator->validated();
        $commentData['post_id'] = $postId;
        $commentData['user_id'] = Auth::id();
        
        $comment = PostComment::create($commentData);
        
        // Load user relationship
        $comment->load('user:id,name,profile_photo');

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    /**
     * Display the specified comment.
     */
    public function show($postId, $id)
    {
        $comment = PostComment::with('user:id,name,profile_photo')
                            ->where('post_id', $postId)
                            ->findOrFail($id);
        
        return response()->json([
            'status' => true,
            'message' => 'Comment retrieved successfully',
            'data' => $comment
        ], 200);
    }

    /**
     * Update the specified comment in storage.
     */
    public function update(Request $request, $postId, $id)
    {
        $comment = PostComment::where('post_id', $postId)
                            ->findOrFail($id);
        
        // Check if user is authorized to update this comment
        if ($comment->user_id !== Auth::id()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update this comment'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $commentData = $validator->validated();
        
        $comment->update($commentData);
        
        // Load user relationship
        $comment->load('user:id,name,profile_photo');

        return response()->json([
            'status' => true,
            'message' => 'Comment updated successfully',
            'data' => $comment
        ], 200);
    }

    /**
     * Remove the specified comment from storage.
     */
    public function destroy($postId, $id)
    {
        $comment = PostComment::where('post_id', $postId)
                            ->findOrFail($id);
        
        // Check if user is authorized to delete this comment
        if ($comment->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this comment'
            ], 403);
        }
        
        $comment->delete();

        return response()->json([
            'status' => true,
            'message' => 'Comment deleted successfully'
        ], 200);
    }
    
    /**
     * Get comments by a specific user.
     */
    public function getUserComments($userId = null)
    {
        // If no user ID is provided, use the authenticated user's ID
        $userId = $userId ?? Auth::id();
        
        $comments = PostComment::with(['user:id,name,profile_photo', 'post:id,content,user_id', 'post.user:id,name'])
                             ->where('user_id', $userId)
                             ->orderBy('created_at', 'desc')
                             ->paginate(20);
        
        return response()->json([
            'status' => true,
            'message' => 'User comments retrieved successfully',
            'data' => $comments
        ], 200);
    }
}