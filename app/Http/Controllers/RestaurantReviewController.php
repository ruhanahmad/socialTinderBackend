<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RestaurantReviewController extends Controller
{
    /**
     * Display a listing of reviews for a specific restaurant.
     */
    public function index($restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $reviews = RestaurantReview::with('user:id,name,profile_photo')
                                  ->where('restaurant_id', $restaurantId)
                                  ->orderBy('created_at', 'desc')
                                  ->paginate(10);
        
        return response()->json([
            'status' => true,
            'message' => 'Reviews retrieved successfully',
            'data' => [
                'restaurant' => $restaurant->only(['id', 'name']),
                'reviews' => $reviews
            ]
        ], 200);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request, $restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
            'visit_date' => 'nullable|date',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user already reviewed this restaurant
        $existingReview = RestaurantReview::where('restaurant_id', $restaurantId)
                                        ->where('user_id', Auth::id())
                                        ->first();
        
        if ($existingReview) {
            return response()->json([
                'status' => false,
                'message' => 'You have already reviewed this restaurant. Please update your existing review.'
            ], 422);
        }
        
        $reviewData = $validator->validated();
        $reviewData['restaurant_id'] = $restaurantId;
        $reviewData['user_id'] = Auth::id();
        
        // Handle photo uploads
        $photoUrls = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('review_photos', 'public');
                $photoUrls[] = $path;
            }
        }
        
        if (!empty($photoUrls)) {
            $reviewData['photos'] = json_encode($photoUrls);
        }
        
        $review = RestaurantReview::create($reviewData);

        return response()->json([
            'status' => true,
            'message' => 'Review submitted successfully',
            'data' => $review
        ], 201);
    }

    /**
     * Display the specified review.
     */
    public function show($restaurantId, $id)
    {
        $review = RestaurantReview::with('user:id,name,profile_photo')
                                ->where('restaurant_id', $restaurantId)
                                ->findOrFail($id);
        
        return response()->json([
            'status' => true,
            'message' => 'Review retrieved successfully',
            'data' => $review
        ], 200);
    }

    /**
     * Update the specified review in storage.
     */
    public function update(Request $request, $restaurantId, $id)
    {
        $review = RestaurantReview::where('restaurant_id', $restaurantId)
                                ->where('user_id', Auth::id())
                                ->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string',
            'visit_date' => 'nullable|date',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $reviewData = $validator->validated();
        
        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $photoUrls = [];
            
            // Keep existing photos if any
            if ($review->photos) {
                $photoUrls = json_decode($review->photos, true);
            }
            
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('review_photos', 'public');
                $photoUrls[] = $path;
            }
            
            $reviewData['photos'] = json_encode($photoUrls);
        }
        
        $review->update($reviewData);

        return response()->json([
            'status' => true,
            'message' => 'Review updated successfully',
            'data' => $review
        ], 200);
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy($restaurantId, $id)
    {
        $review = RestaurantReview::where('restaurant_id', $restaurantId)
                                ->where('user_id', Auth::id())
                                ->findOrFail($id);
        
        $review->delete();

        return response()->json([
            'status' => true,
            'message' => 'Review deleted successfully'
        ], 200);
    }
    
    /**
     * Get the current user's review for a restaurant.
     */
    public function getUserReview($restaurantId)
    {
        $review = RestaurantReview::where('restaurant_id', $restaurantId)
                                ->where('user_id', Auth::id())
                                ->first();
        
        if (!$review) {
            return response()->json([
                'status' => false,
                'message' => 'You have not reviewed this restaurant yet'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'message' => 'User review retrieved successfully',
            'data' => $review
        ], 200);
    }
}