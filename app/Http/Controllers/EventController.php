<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * Display a listing of events.
     */
    public function index(Request $request)
    {
        $query = Event::query();
        
        // Apply filters if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }
        
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }
        
        // Get paginated results
        $events = $query->with(['tickets'])
                       ->orderBy('start_date')
                       ->paginate(10);
        
        return response()->json([
            'status' => true,
            'message' => 'Events retrieved successfully',
            'data' => $events
        ], 200);
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'max_attendees' => 'nullable|integer|min:1',
            'is_featured' => 'sometimes|boolean',
            'is_published' => 'sometimes|boolean',
            'organizer_name' => 'required|string|max:255',
            'organizer_phone' => 'nullable|string|max:20',
            'organizer_email' => 'required|email|max:255',
            'organizer_website' => 'nullable|url|max:255',
            'promoter_id' => 'nullable|exists:users,id',
            'ticket_types' => 'nullable|array',
            'ticket_types.*.name' => 'required|string|max:100',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity' => 'required|integer|min:1',
            'ticket_types.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $eventData = $validator->validated();
        $eventData['user_id'] = Auth::id(); // Set creator
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('event_images', 'public');
            $eventData['image'] = $path;
        }
        
        // Remove ticket_types from event data
        $ticketTypes = null;
        if (isset($eventData['ticket_types'])) {
            $ticketTypes = $eventData['ticket_types'];
            unset($eventData['ticket_types']);
        }
        
        // Create event
        $event = Event::create($eventData);
        
        // Create ticket types if provided
        if ($ticketTypes) {
            foreach ($ticketTypes as $ticketType) {
                $event->tickets()->create([
                    'name' => $ticketType['name'],
                    'price' => $ticketType['price'],
                    'quantity_available' => $ticketType['quantity'],
                    'quantity_sold' => 0,
                    'description' => $ticketType['description'] ?? null,
                ]);
            }
        }

        // Load tickets relationship
        $event->load('tickets');

        return response()->json([
            'status' => true,
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show($id)
    {
        $event = Event::with(['tickets', 'promoter:id,name,email'])
                     ->findOrFail($id);
        
        // Increment view count
        $event->increment('views');
        
        return response()->json([
            'status' => true,
            'message' => 'Event retrieved successfully',
            'data' => $event
        ], 200);
    }

    /**
     * Update the specified event in storage.
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        
        // Check if user is authorized to update this event
        if ($event->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update this event'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'location' => 'sometimes|string|max:255',
            'venue' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'max_attendees' => 'nullable|integer|min:1',
            'is_featured' => 'sometimes|boolean',
            'is_published' => 'sometimes|boolean',
            'organizer_name' => 'sometimes|string|max:255',
            'organizer_phone' => 'nullable|string|max:20',
            'organizer_email' => 'sometimes|email|max:255',
            'organizer_website' => 'nullable|url|max:255',
            'promoter_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $eventData = $validator->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }
            
            $path = $request->file('image')->store('event_images', 'public');
            $eventData['image'] = $path;
        }
        
        $event->update($eventData);

        return response()->json([
            'status' => true,
            'message' => 'Event updated successfully',
            'data' => $event
        ], 200);
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        
        // Check if user is authorized to delete this event
        if ($event->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this event'
            ], 403);
        }
        
        // Delete associated image if exists
        if ($event->image) {
            Storage::disk('public')->delete($event->image);
        }
        
        $event->delete();

        return response()->json([
            'status' => true,
            'message' => 'Event deleted successfully'
        ], 200);
    }
    
    /**
     * Get featured events.
     */
    public function getFeaturedEvents()
    {
        $events = Event::with(['tickets'])
                      ->where('is_featured', true)
                      ->where('is_published', true)
                      ->where('end_date', '>=', now()->format('Y-m-d'))
                      ->orderBy('start_date')
                      ->take(5)
                      ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Featured events retrieved successfully',
            'data' => $events
        ], 200);
    }
    
    /**
     * Get upcoming events.
     */
    public function getUpcomingEvents()
    {
        $events = Event::with(['tickets'])
                      ->where('is_published', true)
                      ->where('start_date', '>=', now()->format('Y-m-d'))
                      ->orderBy('start_date')
                      ->take(10)
                      ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Upcoming events retrieved successfully',
            'data' => $events
        ], 200);
    }
    
    /**
     * Get event categories.
     */
    public function getCategories()
    {
        $categories = Event::select('category')
                          ->distinct()
                          ->pluck('category')
                          ->filter();
        
        return response()->json([
            'status' => true,
            'message' => 'Event categories retrieved successfully',
            'data' => $categories
        ], 200);
    }
}