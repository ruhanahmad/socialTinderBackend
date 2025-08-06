<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Get all users from the same country as logged-in user
     */
    public function getUsersByCountry(Request $request)
    {
        $user = $request->user();
        $country = $request->input('country', $user->country);

        $users = User::where('country', $country)
                    ->where('id', '!=', $user->id) // Exclude current user
                    ->select('id', 'name', 'email', 'country', 'phone_number', 'profile_photo', 'description')
                    ->get();

        // Add like status for each user
        $users->each(function ($userData) use ($user) {
            $userData->is_liked = $user->hasLiked($userData->id);
            $userData->is_liked_by = $user->hasBeenLikedBy($userData->id);
        });

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'data' => [
                'users' => $users,
                'filtered_country' => $country
            ]
        ], 200);
    }

    /**
     * Update user profile (photo and description)
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:1000',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $updateData = [];

        // Handle description update
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Store new photo
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $updateData['profile_photo'] = $path;
        }

        // Update user
        $user->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'country' => $user->country,
                    'phone_number' => $user->phone_number,
                    'profile_photo' => $user->profile_photo ? url('storage/' . $user->profile_photo) : null,
                    'description' => $user->description,
                ]
            ]
        ], 200);
    }

    /**
     * Like or dislike a user
     */
    public function likeUser(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:like,dislike'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = $request->user();
        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($currentUser->id == $targetUser->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot like/dislike yourself'
            ], 400);
        }

        $action = $request->action;

        // Check if already liked/disliked
        $existingLike = $currentUser->likedUsers()
                                   ->where('liked_user_id', $userId)
                                   ->first();

        if ($existingLike) {
            // Update existing like/dislike
            $currentUser->likedUsers()->updateExistingPivot($userId, [
                'action' => $action,
                'updated_at' => now()
            ]);
        } else {
            // Create new like/dislike
            $currentUser->likedUsers()->attach($userId, [
                'action' => $action,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => "User {$action}d successfully",
            'data' => [
                'action' => $action,
                'target_user_id' => $userId
            ]
        ], 200);
    }

    /**
     * Remove like/dislike
     */
    public function removeLike(Request $request, $userId)
    {
        $currentUser = $request->user();
        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Remove the like/dislike
        $currentUser->likedUsers()->detach($userId);

        return response()->json([
            'status' => true,
            'message' => 'Like/dislike removed successfully',
            'data' => [
                'target_user_id' => $userId
            ]
        ], 200);
    }

    /**
     * Get user's likes and dislikes
     */
    public function getMyLikes(Request $request)
    {
        $user = $request->user();

        $likes = $user->likedUsers()
                     ->select('users.id', 'users.name', 'users.email', 'users.country', 'users.profile_photo', 'user_likes.action', 'user_likes.created_at')
                     ->get();

        $likedBy = $user->likedByUsers()
                       ->select('users.id', 'users.name', 'users.email', 'users.country', 'users.profile_photo', 'user_likes.action', 'user_likes.created_at')
                       ->get();

        return response()->json([
            'status' => true,
            'message' => 'Likes retrieved successfully',
            'data' => [
                'my_likes' => $likes,
                'liked_by' => $likedBy
            ]
        ], 200);
    }

    /**
     * Get available countries for filtering
     */
    public function getCountries(Request $request)
    {
        $countries = User::distinct()
                        ->whereNotNull('country')
                        ->pluck('country')
                        ->sort()
                        ->values();

        return response()->json([
            'status' => true,
            'message' => 'Countries retrieved successfully',
            'data' => [
                'countries' => $countries
            ]
        ], 200);
    }
    
    /**
     * Filter users by various criteria (age, gender, distance, etc.)
     */
    public function filterUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_age' => 'nullable|integer|min:18|max:100',
            'max_age' => 'nullable|integer|min:18|max:100',
            'gender' => 'nullable|string|in:male,female,other',
            'nationality' => 'nullable|string',
            'max_distance' => 'nullable|integer|min:1',
            'min_height' => 'nullable|integer|min:100',
            'max_height' => 'nullable|integer|min:100',
            'relationship_status' => 'nullable|string',
            'interests' => 'nullable|array',
            'interests.*' => 'string',
            'location' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = $request->user();
        $query = User::where('id', '!=', $currentUser->id); // Exclude current user
        
        // Apply age filter
        if ($request->has('min_age')) {
            $query->where('age', '>=', $request->min_age);
        }
        
        if ($request->has('max_age')) {
            $query->where('age', '<=', $request->max_age);
        }
        
        // Apply gender filter
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }
        
        // Apply nationality filter
        if ($request->has('nationality')) {
            $query->where('nationality', $request->nationality);
        }
        
        // Apply height filter
        if ($request->has('min_height')) {
            $query->where('height', '>=', $request->min_height);
        }
        
        if ($request->has('max_height')) {
            $query->where('height', '<=', $request->max_height);
        }
        
        // Apply relationship status filter
        if ($request->has('relationship_status')) {
            $query->where('relationship_status', $request->relationship_status);
        }
        
        // Apply location filter
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }
        
        // Apply interests filter (using JSON contains)
        if ($request->has('interests') && !empty($request->interests)) {
            foreach ($request->interests as $interest) {
                $query->whereJsonContains('interests', $interest);
            }
        }
        
        // Get users with basic info
        $users = $query->select(
                'id', 'name', 'age', 'gender', 'nationality', 'height',
                'profile_photo', 'location', 'latitude', 'longitude',
                'relationship_status', 'interests', 'bio', 'last_active'
            )
            ->get();
        
        // Filter by distance if needed
        if ($request->has('max_distance') && $currentUser->latitude && $currentUser->longitude) {
            $maxDistance = $request->max_distance;
            
            $users = $users->filter(function ($user) use ($currentUser, $maxDistance) {
                $distance = $currentUser->distanceTo($user);
                
                // Add distance to user object for sorting/display
                if ($distance !== null) {
                    $user->distance = round($distance, 1);
                    return $distance <= $maxDistance;
                }
                
                return false; // Exclude users without location data
            });
            
            // Sort by distance
            $users = $users->sortBy('distance')->values();
        }
        
        // Format profile photos
        $users->transform(function ($user) use ($currentUser) {
            $user->profile_photo = $user->profile_photo ? url('storage/' . $user->profile_photo) : null;
            $user->is_liked = $currentUser->hasLiked($user->id);
            $user->is_liked_by = $currentUser->hasBeenLikedBy($user->id);
            $user->is_match = $currentUser->matches()->where('matched_user_id', $user->id)->exists();
            return $user;
        });

        return response()->json([
            'status' => true,
            'message' => 'Users filtered successfully',
            'data' => $users
        ], 200);
    }
    
    /**
     * Get potential matches for swiping
     */
    public function getPotentialMatches(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_age' => 'nullable|integer|min:18|max:100',
            'max_age' => 'nullable|integer|min:18|max:100',
            'gender' => 'nullable|string|in:male,female,other',
            'max_distance' => 'nullable|integer|min:1',
            'nationality' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = $request->user();
        
        // Get users that the current user has not interacted with yet
        $query = User::where('id', '!=', $currentUser->id)
            ->whereNotIn('id', function($query) use ($currentUser) {
                $query->select('liked_user_id')
                    ->from('user_likes')
                    ->where('user_id', $currentUser->id);
            })
            ->whereNotIn('id', function($query) use ($currentUser) {
                $query->select('matched_user_id')
                    ->from('matches')
                    ->where('user_id', $currentUser->id);
            });
        
        // Apply filters
        if ($request->has('min_age')) {
            $query->where('age', '>=', $request->min_age);
        }
        
        if ($request->has('max_age')) {
            $query->where('age', '<=', $request->max_age);
        }
        
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }
        
        if ($request->has('nationality')) {
            $query->where('nationality', $request->nationality);
        }
        
        // Get users with photos
        $users = $query->has('photos')
            ->select(
                'id', 'name', 'age', 'gender', 'nationality', 'height',
                'profile_photo', 'location', 'latitude', 'longitude',
                'relationship_status', 'interests', 'bio', 'last_active'
            )
            ->with(['photos' => function($query) {
                $query->orderBy('is_primary', 'desc')
                      ->orderBy('order', 'asc');
            }])
            ->inRandomOrder() // Randomize order for variety
            ->limit(20) // Limit to 20 potential matches at a time
            ->get();
        
        // Filter by distance if needed
        if ($request->has('max_distance') && $currentUser->latitude && $currentUser->longitude) {
            $maxDistance = $request->max_distance;
            
            $users = $users->filter(function ($user) use ($currentUser, $maxDistance) {
                $distance = $currentUser->distanceTo($user);
                
                // Add distance to user object
                if ($distance !== null) {
                    $user->distance = round($distance, 1);
                    return $distance <= $maxDistance;
                }
                
                return false; // Exclude users without location data
            });
        }
        
        // Format photos and add additional info
        $users->transform(function ($user) {
            $user->profile_photo = $user->profile_photo ? url('storage/' . $user->profile_photo) : null;
            
            // Format all photos
            if ($user->photos) {
                $user->photos->transform(function ($photo) {
                    $photo->photo_url = url('storage/' . $photo->photo_path);
                    return $photo;
                });
            }
            
            return $user;
        });

        return response()->json([
            'status' => true,
            'message' => 'Potential matches retrieved successfully',
            'data' => $users
        ], 200);
    }
    
    /**
     * Get social wall (posts from friends and self)
     */
    public function getSocialWall(Request $request)
    {
        $currentUser = $request->user();
        
        // Get IDs of friends and self
        $friendIds = $currentUser->friends()->pluck('users.id')->toArray();
        $userIds = array_merge([$currentUser->id], $friendIds);
        
        // Get posts from friends and self
        $posts = Post::whereIn('user_id', $userIds)
            ->with(['user:id,name,profile_photo', 'comments' => function($query) {
                $query->with('user:id,name,profile_photo')
                      ->orderBy('created_at', 'desc')
                      ->limit(3); // Limit to 3 most recent comments per post
            }])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        // Add liked_by_me attribute
        $posts->getCollection()->transform(function ($post) use ($currentUser) {
            // Check if current user liked this post
            $post->liked_by_me = $post->likes()->where('user_id', $currentUser->id)->exists();
            
            // Format user profile photo
            if ($post->user && $post->user->profile_photo) {
                $post->user->profile_photo = url('storage/' . $post->user->profile_photo);
            }
            
            // Format comment user profile photos
            if ($post->comments) {
                $post->comments->each(function ($comment) {
                    if ($comment->user && $comment->user->profile_photo) {
                        $comment->user->profile_photo = url('storage/' . $comment->user->profile_photo);
                    }
                });
            }
            
            // Format post images if any
            if ($post->images) {
                $images = json_decode($post->images);
                $formattedImages = [];
                
                foreach ($images as $image) {
                    $formattedImages[] = url('storage/' . $image);
                }
                
                $post->image_urls = $formattedImages;
            }
            
            return $post;
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Social wall retrieved successfully',
            'data' => $posts
        ], 200);
    }
    
    /**
     * Get user's friends list
     */
    public function getFriends(Request $request)
    {
        $currentUser = $request->user();
        
        $friends = $currentUser->friends()
            ->select('users.id', 'name', 'profile_photo', 'last_active', 'bio')
            ->get()
            ->map(function ($friend) {
                $friend->profile_photo = $friend->profile_photo ? url('storage/' . $friend->profile_photo) : null;
                return $friend;
            });
        
        return response()->json([
            'status' => true,
            'message' => 'Friends retrieved successfully',
            'data' => $friends
        ], 200);
    }
    
    /**
     * Add friend by username
     */
    public function addFriend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:users,username'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $currentUser = $request->user();
        $friend = User::where('username', $request->username)->first();
        
        if (!$friend) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        if ($currentUser->id === $friend->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot add yourself as a friend'
            ], 400);
        }
        
        // Check if already friends
        $existingFriendship = DB::table('friendships')
            ->where(function ($query) use ($currentUser, $friend) {
                $query->where('user_id', $currentUser->id)
                      ->where('friend_id', $friend->id);
            })
            ->first();
        
        if ($existingFriendship) {
            return response()->json([
                'status' => false,
                'message' => 'Friend request already sent or friendship already exists'
            ], 400);
        }
        
        // Create friendship (pending status)
        DB::table('friendships')->insert([
            'user_id' => $currentUser->id,
            'friend_id' => $friend->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json([
            'status' => true,
            'message' => 'Friend request sent successfully',
            'data' => [
                'friend' => [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'username' => $friend->username,
                    'profile_photo' => $friend->profile_photo ? url('storage/' . $friend->profile_photo) : null,
                ]
            ]
        ], 200);
    }
    
    /**
     * Get pending friend requests
     */
    public function getPendingFriendRequests(Request $request)
    {
        $currentUser = $request->user();
        
        // Get friend requests sent to the current user
        $pendingRequests = DB::table('friendships')
            ->join('users', 'friendships.user_id', '=', 'users.id')
            ->where('friendships.friend_id', $currentUser->id)
            ->where('friendships.status', 'pending')
            ->select('users.id', 'users.name', 'users.username', 'users.profile_photo', 'friendships.created_at')
            ->get()
            ->map(function ($user) {
                $user->profile_photo = $user->profile_photo ? url('storage/' . $user->profile_photo) : null;
                return $user;
            });
        
        return response()->json([
            'status' => true,
            'message' => 'Pending friend requests retrieved successfully',
            'data' => $pendingRequests
        ], 200);
    }
    
    /**
     * Accept or reject friend request
     */
    public function respondToFriendRequest(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:accept,reject'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $currentUser = $request->user();
        
        // Check if friend request exists
        $friendRequest = DB::table('friendships')
            ->where('user_id', $userId)
            ->where('friend_id', $currentUser->id)
            ->where('status', 'pending')
            ->first();
        
        if (!$friendRequest) {
            return response()->json([
                'status' => false,
                'message' => 'Friend request not found'
            ], 404);
        }
        
        if ($request->action === 'accept') {
            // Update status to accepted
            DB::table('friendships')
                ->where('user_id', $userId)
                ->where('friend_id', $currentUser->id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => now()
                ]);
            
            // Create reverse friendship entry (for mutual friendship)
            DB::table('friendships')->insert([
                'user_id' => $currentUser->id,
                'friend_id' => $userId,
                'status' => 'accepted',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $message = 'Friend request accepted successfully';
        } else {
            // Delete the friend request
            DB::table('friendships')
                ->where('user_id', $userId)
                ->where('friend_id', $currentUser->id)
                ->delete();
            
            $message = 'Friend request rejected successfully';
        }
        
        return response()->json([
            'status' => true,
            'message' => $message
        ], 200);
    }
}