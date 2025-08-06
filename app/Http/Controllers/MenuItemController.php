<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    /**
     * Display a listing of menu items for a specific restaurant.
     */
    public function index($restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $menuItems = MenuItem::where('restaurant_id', $restaurantId)
                            ->orderBy('category')
                            ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Menu items retrieved successfully',
            'data' => [
                'restaurant' => $restaurant->only(['id', 'name']),
                'menu_items' => $menuItems
            ]
        ], 200);
    }

    /**
     * Store a newly created menu item in storage.
     */
    public function store(Request $request, $restaurantId)
    {
        // Verify restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'dietary_info' => 'nullable|array',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $menuItemData = $validator->validated();
        $menuItemData['restaurant_id'] = $restaurantId;
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu_items', 'public');
            $menuItemData['image'] = $path;
        }
        
        // Convert arrays to JSON for storage
        if (isset($menuItemData['dietary_info'])) {
            $menuItemData['dietary_info'] = json_encode($menuItemData['dietary_info']);
        }
        
        if (isset($menuItemData['ingredients'])) {
            $menuItemData['ingredients'] = json_encode($menuItemData['ingredients']);
        }
        
        if (isset($menuItemData['allergens'])) {
            $menuItemData['allergens'] = json_encode($menuItemData['allergens']);
        }
        
        $menuItem = MenuItem::create($menuItemData);

        return response()->json([
            'status' => true,
            'message' => 'Menu item created successfully',
            'data' => $menuItem
        ], 201);
    }

    /**
     * Display the specified menu item.
     */
    public function show($restaurantId, $id)
    {
        $menuItem = MenuItem::where('restaurant_id', $restaurantId)
                          ->findOrFail($id);
        
        return response()->json([
            'status' => true,
            'message' => 'Menu item retrieved successfully',
            'data' => $menuItem
        ], 200);
    }

    /**
     * Update the specified menu item in storage.
     */
    public function update(Request $request, $restaurantId, $id)
    {
        $menuItem = MenuItem::where('restaurant_id', $restaurantId)
                          ->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'dietary_info' => 'nullable|array',
            'ingredients' => 'nullable|array',
            'allergens' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $menuItemData = $validator->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            
            $path = $request->file('image')->store('menu_items', 'public');
            $menuItemData['image'] = $path;
        }
        
        // Convert arrays to JSON for storage
        if (isset($menuItemData['dietary_info'])) {
            $menuItemData['dietary_info'] = json_encode($menuItemData['dietary_info']);
        }
        
        if (isset($menuItemData['ingredients'])) {
            $menuItemData['ingredients'] = json_encode($menuItemData['ingredients']);
        }
        
        if (isset($menuItemData['allergens'])) {
            $menuItemData['allergens'] = json_encode($menuItemData['allergens']);
        }
        
        $menuItem->update($menuItemData);

        return response()->json([
            'status' => true,
            'message' => 'Menu item updated successfully',
            'data' => $menuItem
        ], 200);
    }

    /**
     * Remove the specified menu item from storage.
     */
    public function destroy($restaurantId, $id)
    {
        $menuItem = MenuItem::where('restaurant_id', $restaurantId)
                          ->findOrFail($id);
        
        // Delete associated image if exists
        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }
        
        $menuItem->delete();

        return response()->json([
            'status' => true,
            'message' => 'Menu item deleted successfully'
        ], 200);
    }
}