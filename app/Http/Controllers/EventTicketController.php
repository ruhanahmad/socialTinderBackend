<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventTicketController extends Controller
{
    /**
     * Display a listing of tickets for a specific event.
     */
    public function index($eventId)
    {
        // Verify event exists
        $event = Event::findOrFail($eventId);
        
        $tickets = EventTicket::where('event_id', $eventId)
                            ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Tickets retrieved successfully',
            'data' => [
                'event' => $event->only(['id', 'title', 'start_date', 'end_date']),
                'tickets' => $tickets
            ]
        ], 200);
    }

    /**
     * Store a newly created ticket in storage.
     */
    public function store(Request $request, $eventId)
    {
        // Verify event exists
        $event = Event::findOrFail($eventId);
        
        // Check if user is authorized to add tickets to this event
        if ($event->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to add tickets to this event'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'quantity_available' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after_or_equal:sale_start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticketData = $validator->validated();
        $ticketData['event_id'] = $eventId;
        $ticketData['quantity_sold'] = 0;
        
        $ticket = EventTicket::create($ticketData);

        return response()->json([
            'status' => true,
            'message' => 'Ticket created successfully',
            'data' => $ticket
        ], 201);
    }

    /**
     * Display the specified ticket.
     */
    public function show($eventId, $id)
    {
        $ticket = EventTicket::where('event_id', $eventId)
                           ->findOrFail($id);
        
        return response()->json([
            'status' => true,
            'message' => 'Ticket retrieved successfully',
            'data' => $ticket
        ], 200);
    }

    /**
     * Update the specified ticket in storage.
     */
    public function update(Request $request, $eventId, $id)
    {
        $ticket = EventTicket::where('event_id', $eventId)
                           ->findOrFail($id);
        
        $event = Event::findOrFail($eventId);
        
        // Check if user is authorized to update tickets for this event
        if ($event->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update tickets for this event'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'quantity_available' => 'sometimes|integer|min:' . $ticket->quantity_sold,
            'description' => 'nullable|string',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after_or_equal:sale_start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticketData = $validator->validated();
        
        $ticket->update($ticketData);

        return response()->json([
            'status' => true,
            'message' => 'Ticket updated successfully',
            'data' => $ticket
        ], 200);
    }

    /**
     * Remove the specified ticket from storage.
     */
    public function destroy($eventId, $id)
    {
        $ticket = EventTicket::where('event_id', $eventId)
                           ->findOrFail($id);
        
        $event = Event::findOrFail($eventId);
        
        // Check if user is authorized to delete tickets for this event
        if ($event->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete tickets for this event'
            ], 403);
        }
        
        // Check if tickets have been sold
        if ($ticket->quantity_sold > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete ticket type because tickets have already been sold'
            ], 422);
        }
        
        $ticket->delete();

        return response()->json([
            'status' => true,
            'message' => 'Ticket deleted successfully'
        ], 200);
    }
    
    /**
     * Purchase tickets for an event.
     */
    public function purchaseTickets(Request $request, $eventId, $ticketId)
    {
        $ticket = EventTicket::where('event_id', $eventId)
                           ->findOrFail($ticketId);
        
        $event = Event::findOrFail($eventId);
        
        // Check if event is still active
        if ($event->end_date < now()->format('Y-m-d')) {
            return response()->json([
                'status' => false,
                'message' => 'This event has already ended'
            ], 422);
        }
        
        // Check if ticket is still on sale
        if ($ticket->sale_end_date && $ticket->sale_end_date < now()->format('Y-m-d')) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket sales have ended for this ticket type'
            ], 422);
        }
        
        // Check if ticket is active
        if (!$ticket->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'This ticket type is not currently available'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:credit_card,paypal,bank_transfer',
            'payment_details' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $quantity = $request->quantity;
        
        // Check if enough tickets are available
        $availableTickets = $ticket->quantity_available - $ticket->quantity_sold;
        if ($quantity > $availableTickets) {
            return response()->json([
                'status' => false,
                'message' => 'Not enough tickets available. Only ' . $availableTickets . ' tickets left.'
            ], 422);
        }
        
        // Process payment (simplified for this example)
        // In a real application, you would integrate with a payment gateway
        $totalAmount = $ticket->price * $quantity;
        
        // Record the purchase
        $purchase = $ticket->purchases()->create([
            'user_id' => Auth::id(),
            'quantity' => $quantity,
            'total_amount' => $totalAmount,
            'payment_method' => $request->payment_method,
            'payment_details' => json_encode($request->payment_details),
            'status' => 'completed', // In a real app, this would be 'pending' until payment confirmation
        ]);
        
        // Update ticket quantity sold
        $ticket->increment('quantity_sold', $quantity);
        
        return response()->json([
            'status' => true,
            'message' => 'Tickets purchased successfully',
            'data' => [
                'purchase_id' => $purchase->id,
                'event' => $event->only(['id', 'title', 'start_date', 'end_date', 'venue']),
                'ticket_type' => $ticket->name,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'purchase_date' => now()->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }
    
    /**
     * Get user's purchased tickets.
     */
    public function getUserTickets()
    {
        $purchases = Auth::user()->ticketPurchases()
                               ->with(['ticket.event'])
                               ->orderBy('created_at', 'desc')
                               ->get()
                               ->map(function ($purchase) {
                                   return [
                                       'purchase_id' => $purchase->id,
                                       'event' => $purchase->ticket->event->only(['id', 'title', 'start_date', 'end_date', 'venue', 'image']),
                                       'ticket_type' => $purchase->ticket->name,
                                       'quantity' => $purchase->quantity,
                                       'total_amount' => $purchase->total_amount,
                                       'purchase_date' => $purchase->created_at->format('Y-m-d H:i:s'),
                                       'status' => $purchase->status,
                                   ];
                               });
        
        return response()->json([
            'status' => true,
            'message' => 'User tickets retrieved successfully',
            'data' => $purchases
        ], 200);
    }
}