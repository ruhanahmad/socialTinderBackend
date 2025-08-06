<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantSpecial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RestaurantSpecialController extends Controller
{
    /**
     * Display a listing of specials for a specific restaurant.
     */
    public function index($restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $specials = RestaurantSpecial::where('restaurant_id', $restaurantId)
                                    ->orderBy('start_date')
                                    ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Specials retrieved successfully',
            'data' => [
                'restaurant' => $restaurant->only(['id', 'name']),
                'specials' => $specials
            ]
        ], 200);
    }

    /**
     * Store a newly created special in storage.
     */
    public function store(Request $request, $restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'discount_type' => 'required|in:percentage,fixed_amount,buy_one_get_one,free_item',
            'discount_value' => 'required_unless:discount_type,buy_one_get_one,free_item|nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'terms_conditions' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'days_valid' => 'nullable|array',
            'days_valid.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $specialData = $validator->validated();
        $specialData['restaurant_id'] = $restaurantId;
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('special_offers', 'public');
            $specialData['image'] = $path;
        }
        
        // Convert days_valid array to JSON for storage
        if (isset($specialData['days_valid'])) {
            $specialData['days_valid'] = json_encode($specialData['days_valid']);
        }
        
        $special = RestaurantSpecial::create($specialData);

        return response()->json([
            'status' => true,
            'message' => 'Special offer created successfully',
            'data' => $special
        ], 201);
    }

    /**
     * Display the specified special.
     */
    public function show($restaurantId, $id)
    {
        $special = RestaurantSpecial::where('restaurant_id', $restaurantId)
                                  ->findOrFail($id);
        
        return response()->json([
            'status' => true,
            'message' => 'Special offer retrieved successfully',
            'data' => $special
        ], 200);
    }

    /**
     * Update the specified special in storage.
     */
    public function update(Request $request, $restaurantId, $id)
    {
        $special = RestaurantSpecial::where('restaurant_id', $restaurantId)
                                  ->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'discount_type' => 'sometimes|in:percentage,fixed_amount,buy_one_get_one,free_item',
            'discount_value' => 'required_if:discount_type,percentage,fixed_amount|nullable|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'terms_conditions' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'days_valid' => 'nullable|array',
            'days_valid.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $specialData = $validator->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($special->image) {
                Storage::disk('public')->delete($special->image);
            }
            
            $path = $request->file('image')->store('special_offers', 'public');
            $specialData['image'] = $path;
        }
        
        // Convert days_valid array to JSON for storage
        if (isset($specialData['days_valid'])) {
            $specialData['days_valid'] = json_encode($specialData['days_valid']);
        }
        
        $special->update($specialData);

        return response()->json([
            'status' => true,
            'message' => 'Special offer updated successfully',
            'data' => $special
        ], 200);
    }

    /**
     * Remove the specified special from storage.
     */
    public function destroy($restaurantId, $id)
    {
        $special = RestaurantSpecial::where('restaurant_id', $restaurantId)
                                  ->findOrFail($id);
        
        // Delete associated image if exists
        if ($special->image) {
            Storage::disk('public')->delete($special->image);
        }
        
        $special->delete();

        return response()->json([
            'status' => true,
            'message' => 'Special offer deleted successfully'
        ], 200);
    }
    
    /**
     * Get active specials across all restaurants.
     */
    public function getActiveSpecials()
    {
        $today = now()->format('Y-m-d');
        $dayOfWeek = strtolower(now()->format('l'));
        
        $specials = RestaurantSpecial::with('restaurant:id,name,logo,address')
                                    ->where('is_active', true)
                                    ->where('start_date', '<=', $today)
                                    ->where('end_date', '>=', $today)
                                    ->get()
                                    ->filter(function($special) use ($dayOfWeek) {
                                        // If days_valid is null or empty, it's valid for all days
                                        if (!$special->days_valid) {
                                            return true;
                                        }
                                        
                                        $validDays = json_decode($special->days_valid, true);
                                        return in_array($dayOfWeek, $validDays);
                                    });
        
        return response()->json([
            'status' => true,
            'message' => 'Active specials retrieved successfully',
            'data' => $specials
        ], 200);
    }
}