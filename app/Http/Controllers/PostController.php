<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index(Request $request)
    {
        $query = Post::query();
        
        // Apply filters if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Get paginated results with relationships
        $posts = $query->with(['user:id,name,profile_photo', 'likes', 'comments.user:id,name,profile_photo'])
                      ->withCount(['likes', 'comments'])
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);
        
        // Add liked_by_me attribute
        $userId = Auth::id();
        $posts->getCollection()->transform(function ($post) use ($userId) {
            $post->liked_by_me = $post->likes->contains('user_id', $userId);
            // Remove the likes collection to reduce response size
            unset($post->likes);
            return $post;
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Posts retrieved successfully',
            'data' => $posts
        ], 200);
    }

    /**
     * Store a newly created post in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'location' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $postData = $validator->validated();
        $postData['user_id'] = Auth::id();
        
        // Handle image uploads
        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('post_images', 'public');
                $imageUrls[] = $path;
            }
        }
        
        if (!empty($imageUrls)) {
            $postData['images'] = json_encode($imageUrls);
        }
        
        // Convert tags array to JSON for storage
        if (isset($postData['tags'])) {
            $postData['tags'] = json_encode($postData['tags']);
        }
        
        $post = Post::create($postData);
        
        // Load relationships
        $post->load(['user:id,name,profile_photo']);
        $post->loadCount(['likes', 'comments']);
        $post->liked_by_me = false;

        return response()->json([
            'status' => true,
            'message' => 'Post created successfully',
            'data' => $post
        ], 201);
    }

    /**
     * Display the specified post.
     */
    public function show($id)
    {
        $post = Post::with(['user:id,name,profile_photo', 'comments.user:id,name,profile_photo'])
                   ->withCount(['likes', 'comments'])
                   ->findOrFail($id);
        
        // Add liked_by_me attribute
        $userId = Auth::id();
        $post->liked_by_me = PostLike::where('post_id', $id)
                                  ->where('user_id', $userId)
                                  ->exists();
        
        return response()->json([
            'status' => true,
            'message' => 'Post retrieved successfully',
            'data' => $post
        ], 200);
    }

    /**
     * Update the specified post in storage.
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        // Check if user is authorized to update this post
        if ($post->user_id !== Auth::id()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update this post'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'sometimes|string',
            'location' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $postData = $validator->validated();
        
        // Convert tags array to JSON for storage
        if (isset($postData['tags'])) {
            $postData['tags'] = json_encode($postData['tags']);
        }
        
        $post->update($postData);
        
        // Load relationships
        $post->load(['user:id,name,profile_photo']);
        $post->loadCount(['likes', 'comments']);
        $post->liked_by_me = PostLike::where('post_id', $id)
                                  ->where('user_id', Auth::id())
                                  ->exists();

        return response()->json([
            'status' => true,
            'message' => 'Post updated successfully',
            'data' => $post
        ], 200);
    }

    /**
     * Remove the specified post from storage.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        
        // Check if user is authorized to delete this post
        if ($post->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this post'
            ], 403);
        }
        
        // Delete associated images if exists
        if ($post->images) {
            $images = json_decode($post->images, true);
            foreach ($images as $image) {
                Storage::disk('public')->delete($image);
            }
        }
        
        $post->delete();

        return response()->json([
            'status' => true,
            'message' => 'Post deleted successfully'
        ], 200);
    }
    
    /**
     * Like or unlike a post.
     */
    public function toggleLike($id)
    {
        $post = Post::findOrFail($id);
        $userId = Auth::id();
        
        $existingLike = PostLike::where('post_id', $id)
                              ->where('user_id', $userId)
                              ->first();
        
        if ($existingLike) {
            // Unlike the post
            $existingLike->delete();
            $action = 'unliked';
        } else {
            // Like the post
            PostLike::create([
                'post_id' => $id,
                'user_id' => $userId
            ]);
            $action = 'liked';
        }
        
        // Get updated like count
        $likeCount = PostLike::where('post_id', $id)->count();
        
        return response()->json([
            'status' => true,
            'message' => 'Post ' . $action . ' successfully',
            'data' => [
                'post_id' => $id,
                'liked_by_me' => ($action === 'liked'),
                'likes_count' => $likeCount
            ]
        ], 200);
    }
    
    /**
     * Get users who liked a post.
     */
    public function getLikes($id)
    {
        $post = Post::findOrFail($id);
        
        $likes = PostLike::where('post_id', $id)
                        ->with('user:id,name,profile_photo')
                        ->get()
                        ->pluck('user');
        
        return response()->json([
            'status' => true,
            'message' => 'Post likes retrieved successfully',
            'data' => $likes
        ], 200);
    }
    
    /**
     * Get feed posts (from followed users and own posts).
     */
    public function getFeed()
    {
        $userId = Auth::id();
        $user = Auth::user();
        
        // Get IDs of users being followed
        $followingIds = $user->following()->pluck('users.id')->toArray();
        
        // Add own user ID to see own posts in feed
        $followingIds[] = $userId;
        
        $posts = Post::whereIn('user_id', $followingIds)
                    ->with(['user:id,name,profile_photo', 'comments.user:id,name,profile_photo'])
                    ->withCount(['likes', 'comments'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
        
        // Add liked_by_me attribute
        $posts->getCollection()->transform(function ($post) use ($userId) {
            $post->liked_by_me = PostLike::where('post_id', $post->id)
                                      ->where('user_id', $userId)
                                      ->exists();
            return $post;
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Feed retrieved successfully',
            'data' => $posts
        ], 200);
    }
    
    /**
     * Get trending posts based on likes and comments.
     */
    public function getTrending()
    {
        $userId = Auth::id();
        
        // Get posts from the last 7 days with high engagement
        $posts = Post::where('created_at', '>=', now()->subDays(7))
                    ->withCount(['likes', 'comments'])
                    ->with(['user:id,name,profile_photo'])
                    ->orderByRaw('likes_count + comments_count DESC')
                    ->take(10)
                    ->get();
        
        // Add liked_by_me attribute
        $posts->transform(function ($post) use ($userId) {
            $post->liked_by_me = PostLike::where('post_id', $post->id)
                                      ->where('user_id', $userId)
                                      ->exists();
            return $post;
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Trending posts retrieved successfully',
            'data' => $posts
        ], 200);
    }
}