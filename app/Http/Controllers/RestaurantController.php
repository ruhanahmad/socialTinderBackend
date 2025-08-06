<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the restaurants.
     */
    public function index(Request $request)
    {
        $query = Restaurant::query();
        
        // Apply filters if provided
        if ($request->has('cuisine_type')) {
            $query->where('cuisine_type', $request->cuisine_type);
        }
        
        if ($request->has('price_range')) {
            $query->where('price_range', $request->price_range);
        }
        
        // Get paginated results
        $restaurants = $query->with(['reviews'])
                            ->paginate(10);
        
        // Add computed attributes
        $restaurants->each(function ($restaurant) {
            $restaurant->average_rating = $restaurant->getAverageRatingAttribute();
            $restaurant->reviews_count = $restaurant->getReviewsCountAttribute();
        });
        
        return response()->json([
            'status' => true,
            'message' => 'Restaurants retrieved successfully',
            'data' => $restaurants
        ], 200);
    }

    /**
     * Store a newly created restaurant in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cuisine_type' => 'required|string|max:100',
            'price_range' => 'required|in:$,$$,$$$,$$$$',
            'opening_hours' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $restaurantData = $validator->validated();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('restaurant_logos', 'public');
            $restaurantData['logo'] = $path;
        }
        
        // Handle cover photo upload
        if ($request->hasFile('cover_photo')) {
            $path = $request->file('cover_photo')->store('restaurant_covers', 'public');
            $restaurantData['cover_photo'] = $path;
        }
        
        $restaurant = Restaurant::create($restaurantData);

        return response()->json([
            'status' => true,
            'message' => 'Restaurant created successfully',
            'data' => $restaurant
        ], 201);
    }

    /**
     * Display the specified restaurant.
     */
    public function show($id)
    {
        $restaurant = Restaurant::with(['menuItems', 'specials', 'reviews.user'])
                                ->findOrFail($id);
        
        // Add computed attributes
        $restaurant->average_rating = $restaurant->getAverageRatingAttribute();
        $restaurant->reviews_count = $restaurant->getReviewsCountAttribute();
        
        return response()->json([
            'status' => true,
            'message' => 'Restaurant retrieved successfully',
            'data' => $restaurant
        ], 200);
    }

    /**
     * Update the specified restaurant in storage.
     */
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address' => 'sometimes|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cuisine_type' => 'sometimes|string|max:100',
            'price_range' => 'sometimes|in:$,$$,$$$,$$$$',
            'opening_hours' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $restaurantData = $validator->validated();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($restaurant->logo) {
                Storage::disk('public')->delete($restaurant->logo);
            }
            
            $path = $request->file('logo')->store('restaurant_logos', 'public');
            $restaurantData['logo'] = $path;
        }
        
        // Handle cover photo upload
        if ($request->hasFile('cover_photo')) {
            // Delete old cover photo if exists
            if ($restaurant->cover_photo) {
                Storage::disk('public')->delete($restaurant->cover_photo);
            }
            
            $path = $request->file('cover_photo')->store('restaurant_covers', 'public');
            $restaurantData['cover_photo'] = $path;
        }
        
        $restaurant->update($restaurantData);

        return response()->json([
            'status' => true,
            'message' => 'Restaurant updated successfully',
            'data' => $restaurant
        ], 200);
    }

    /**
     * Remove the specified restaurant from storage.
     */
    public function destroy($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        
        // Delete associated files
        if ($restaurant->logo) {
            Storage::disk('public')->delete($restaurant->logo);
        }
        
        if ($restaurant->cover_photo) {
            Storage::disk('public')->delete($restaurant->cover_photo);
        }
        
        $restaurant->delete();

        return response()->json([
            'status' => true,
            'message' => 'Restaurant deleted successfully'
        ], 200);
    }
}