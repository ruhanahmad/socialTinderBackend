<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Display a listing of messages for a specific conversation.
     */
    public function index($conversationId, Request $request)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        $query = Message::where('conversation_id', $conversationId);
        
        // Apply pagination with latest messages first
        $messages = $query->with('user:id,name,profile_photo')
                        ->orderBy('created_at', 'desc')
                        ->paginate(20);
        
        // Mark messages as read
        Message::where('conversation_id', $conversationId)
              ->where('user_id', '!=', $userId)
              ->where('is_read', false)
              ->update(['is_read' => true]);
        
        return response()->json([
            'status' => true,
            'message' => 'Messages retrieved successfully',
            'data' => $messages
        ], 200);
    }

    /**
     * Store a newly created message in storage.
     */
    public function store(Request $request, $conversationId)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        $validator = Validator::make($request->all(), [
            'content' => 'required_without:attachments|nullable|string',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $messageData = [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'content' => $request->content,
            'is_read' => false,
        ];
        
        // Handle attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $path = $attachment->store('message_attachments', 'public');
                $attachmentPaths[] = [
                    'path' => $path,
                    'name' => $attachment->getClientOriginalName(),
                    'type' => $attachment->getClientMimeType(),
                    'size' => $attachment->getSize(),
                ];
            }
            
            $messageData['attachments'] = json_encode($attachmentPaths);
        }
        
        $message = Message::create($messageData);
        
        // Load user relationship
        $message->load('user:id,name,profile_photo');

        return response()->json([
            'status' => true,
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);
    }

    /**
     * Display the specified message.
     */
    public function show($conversationId, $id)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        $message = Message::with('user:id,name,profile_photo')
                        ->where('conversation_id', $conversationId)
                        ->findOrFail($id);
        
        // Mark message as read if it's not the user's own message
        if ($message->user_id !== $userId && !$message->is_read) {
            $message->is_read = true;
            $message->save();
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Message retrieved successfully',
            'data' => $message
        ], 200);
    }

    /**
     * Update the specified message in storage.
     */
    public function update(Request $request, $conversationId, $id)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        $message = Message::where('conversation_id', $conversationId)
                        ->findOrFail($id);
        
        // Check if user is authorized to update this message
        if ($message->user_id !== $userId) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to update this message'
            ], 403);
        }
        
        // Check if message is too old to edit (e.g., 24 hours)
        $editTimeLimit = now()->subHours(24);
        if ($message->created_at < $editTimeLimit) {
            return response()->json([
                'status' => false,
                'message' => 'This message can no longer be edited'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $message->content = $request->content;
        $message->is_edited = true;
        $message->save();
        
        // Load user relationship
        $message->load('user:id,name,profile_photo');

        return response()->json([
            'status' => true,
            'message' => 'Message updated successfully',
            'data' => $message
        ], 200);
    }

    /**
     * Remove the specified message from storage.
     */
    public function destroy($conversationId, $id)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        $message = Message::where('conversation_id', $conversationId)
                        ->findOrFail($id);
        
        // Check if user is authorized to delete this message
        if ($message->user_id !== $userId && !Auth::user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to delete this message'
            ], 403);
        }
        
        // Delete attachments if any
        if ($message->attachments) {
            $attachments = json_decode($message->attachments, true);
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }
        
        $message->delete();

        return response()->json([
            'status' => true,
            'message' => 'Message deleted successfully'
        ], 200);
    }
    
    /**
     * Mark all messages in a conversation as read.
     */
    public function markAsRead($conversationId)
    {
        $userId = Auth::id();
        
        // Verify conversation exists and user is a participant
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($conversationId);
        
        // Mark all unread messages from other users as read
        $updatedCount = Message::where('conversation_id', $conversationId)
                             ->where('user_id', '!=', $userId)
                             ->where('is_read', false)
                             ->update(['is_read' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Messages marked as read',
            'data' => [
                'updated_count' => $updatedCount
            ]
        ], 200);
    }
    
    /**
     * Get unread message count for the authenticated user.
     */
    public function getUnreadCount()
    {
        $userId = Auth::id();
        
        // Get conversations where user is a participant
        $conversationIds = Conversation::whereHas('participants', function ($query) use ($userId) {
                                     $query->where('user_id', $userId);
                                 })
                                 ->pluck('id');
        
        // Count unread messages in those conversations
        $unreadCounts = Message::whereIn('conversation_id', $conversationIds)
                             ->where('user_id', '!=', $userId)
                             ->where('is_read', false)
                             ->selectRaw('conversation_id, count(*) as count')
                             ->groupBy('conversation_id')
                             ->get()
                             ->pluck('count', 'conversation_id');
        
        $totalUnread = $unreadCounts->sum();
        
        return response()->json([
            'status' => true,
            'message' => 'Unread message count retrieved successfully',
            'data' => [
                'total_unread' => $totalUnread,
                'by_conversation' => $unreadCounts
            ]
        ], 200);
    }
}