<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserPhotoController extends Controller
{
    /**
     * Display a listing of the user's photos.
     */
    public function index(Request $request, $userId = null)
    {
        // If userId is not provided, use authenticated user's ID
        $userId = $userId ?? Auth::id();
        
        $user = User::findOrFail($userId);
        
        // Get photos with ordering
        $photos = $user->photos()
            ->orderBy('is_primary', 'desc')
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($photo) {
                $photo->photo_url = url('storage/' . $photo->photo_path);
                return $photo;
            });
        
        return response()->json([
            'status' => true,
            'message' => 'Photos retrieved successfully',
            'data' => $photos
        ], 200);
    }

    /**
     * Store a newly created photo in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'is_primary' => 'boolean',
            'order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Store the photo
        $path = $request->file('photo')->store('user_photos', 'public');
        
        // If this is set as primary, update all other photos to non-primary
        if ($request->input('is_primary', false)) {
            $user->photos()->update(['is_primary' => false]);
            
            // Also update the user's profile_photo field
            $user->update(['profile_photo' => $path]);
        }
        
        // Create the photo record
        $photo = $user->photos()->create([
            'photo_path' => $path,
            'is_primary' => $request->input('is_primary', false),
            'order' => $request->input('order', 0)
        ]);
        
        // Add URL for convenience
        $photo->photo_url = url('storage/' . $photo->photo_path);

        return response()->json([
            'status' => true,
            'message' => 'Photo uploaded successfully',
            'data' => $photo
        ], 201);
    }

    /**
     * Display the specified photo.
     */
    public function show($id)
    {
        $photo = UserPhoto::findOrFail($id);
        
        // Check if user has permission to view this photo
        // For now, we'll allow viewing any photo, but you could restrict this
        // to friends or matches depending on your app's requirements
        
        // Add URL for convenience
        $photo->photo_url = url('storage/' . $photo->photo_path);

        return response()->json([
            'status' => true,
            'message' => 'Photo retrieved successfully',
            'data' => $photo
        ], 200);
    }

    /**
     * Update the specified photo in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_primary' => 'boolean',
            'order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $photo = UserPhoto::findOrFail($id);
        $user = Auth::user();
        
        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // If setting as primary, update all other photos
        if ($request->has('is_primary') && $request->is_primary) {
            $user->photos()->update(['is_primary' => false]);
            $photo->is_primary = true;
            
            // Also update the user's profile_photo field
            $user->update(['profile_photo' => $photo->photo_path]);
        } else if ($request->has('is_primary')) {
            $photo->is_primary = $request->is_primary;
        }
        
        // Update order if provided
        if ($request->has('order')) {
            $photo->order = $request->order;
        }
        
        $photo->save();
        
        // Add URL for convenience
        $photo->photo_url = url('storage/' . $photo->photo_path);

        return response()->json([
            'status' => true,
            'message' => 'Photo updated successfully',
            'data' => $photo
        ], 200);
    }

    /**
     * Remove the specified photo from storage.
     */
    public function destroy($id)
    {
        $photo = UserPhoto::findOrFail($id);
        $user = Auth::user();
        
        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Delete the file from storage
        Storage::disk('public')->delete($photo->photo_path);
        
        // If this was the primary photo, update user's profile_photo to null
        if ($photo->is_primary) {
            $user->update(['profile_photo' => null]);
            
            // Optionally, set another photo as primary
            $nextPhoto = $user->photos()->where('id', '!=', $id)->first();
            if ($nextPhoto) {
                $nextPhoto->update(['is_primary' => true]);
                $user->update(['profile_photo' => $nextPhoto->photo_path]);
            }
        }
        
        // Delete the record
        $photo->delete();

        return response()->json([
            'status' => true,
            'message' => 'Photo deleted successfully'
        ], 200);
    }
    
    /**
     * Set a photo as the primary profile photo
     */
    public function setPrimary($id)
    {
        $photo = UserPhoto::findOrFail($id);
        $user = Auth::user();
        
        // Check if user owns this photo
        if ($photo->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Update all user photos to non-primary
        $user->photos()->update(['is_primary' => false]);
        
        // Set this photo as primary
        $photo->is_primary = true;
        $photo->save();
        
        // Update user's profile_photo field
        $user->update(['profile_photo' => $photo->photo_path]);
        
        // Add URL for convenience
        $photo->photo_url = url('storage/' . $photo->photo_path);

        return response()->json([
            'status' => true,
            'message' => 'Primary photo set successfully',
            'data' => $photo
        ], 200);
    }
    
    /**
     * Reorder user photos
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo_order' => 'required|array',
            'photo_order.*.id' => 'required|exists:user_photos,id',
            'photo_order.*.order' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $photoOrder = $request->photo_order;
        
        // Verify all photos belong to the user
        $photoIds = array_column($photoOrder, 'id');
        $userPhotoCount = $user->photos()->whereIn('id', $photoIds)->count();
        
        if (count($photoIds) !== $userPhotoCount) {
            return response()->json([
                'status' => false,
                'message' => 'One or more photos do not belong to the user'
            ], 403);
        }
        
        // Update the order of each photo
        foreach ($photoOrder as $item) {
            UserPhoto::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }
        
        // Get updated photos
        $photos = $user->photos()
            ->orderBy('is_primary', 'desc')
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($photo) {
                $photo->photo_url = url('storage/' . $photo->photo_path);
                return $photo;
            });

        return response()->json([
            'status' => true,
            'message' => 'Photos reordered successfully',
            'data' => $photos
        ], 200);
    }
}