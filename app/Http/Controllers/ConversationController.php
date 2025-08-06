<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    /**
     * Display a listing of the user's conversations.
     */
    public function index()
    {
        $userId = Auth::id();
        
        $conversations = Conversation::whereHas('participants', function ($query) use ($userId) {
                                    $query->where('user_id', $userId);
                                })
                                ->with(['participants.user:id,name,profile_photo'])
                                ->withCount(['messages'])
                                ->get();
        
        // Add last message and unread count to each conversation
        $conversations->transform(function ($conversation) use ($userId) {
            // Get last message
            $lastMessage = $conversation->messages()
                                      ->orderBy('created_at', 'desc')
                                      ->first();
            
            // Get unread count
            $unreadCount = $conversation->messages()
                                      ->where('user_id', '!=', $userId)
                                      ->where('is_read', false)
                                      ->count();
            
            // Add attributes
            $conversation->last_message = $lastMessage;
            $conversation->unread_count = $unreadCount;
            
            // Format participants to exclude current user
            $otherParticipants = $conversation->participants->filter(function ($participant) use ($userId) {
                return $participant->user_id != $userId;
            })->map(function ($participant) {
                return $participant->user;
            })->values();
            
            $conversation->other_participants = $otherParticipants;
            
            // Remove the original participants to reduce response size
            unset($conversation->participants);
            
            return $conversation;
        });
        
        // Sort by last message date
        $conversations = $conversations->sortByDesc(function ($conversation) {
            return $conversation->last_message ? $conversation->last_message->created_at : $conversation->created_at;
        })->values();
        
        return response()->json([
            'status' => true,
            'message' => 'Conversations retrieved successfully',
            'data' => $conversations
        ], 200);
    }

    /**
     * Store a newly created conversation in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'is_group' => 'sometimes|boolean',
            'group_name' => 'required_if:is_group,true|nullable|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $participants = $request->participants;
        
        // Add current user to participants if not already included
        if (!in_array($userId, $participants)) {
            $participants[] = $userId;
        }
        
        // Check if this is a one-on-one conversation that already exists
        if (count($participants) === 2 && !$request->is_group) {
            $existingConversation = $this->findExistingOneOnOneConversation($participants[0], $participants[1]);
            
            if ($existingConversation) {
                // Add new message to existing conversation
                $message = new Message([
                    'conversation_id' => $existingConversation->id,
                    'user_id' => $userId,
                    'content' => $request->message,
                    'is_read' => false,
                ]);
                
                $message->save();
                
                // Load the conversation with participants
                $existingConversation->load(['participants.user:id,name,profile_photo']);
                
                // Format participants to exclude current user
                $otherParticipants = $existingConversation->participants->filter(function ($participant) use ($userId) {
                    return $participant->user_id != $userId;
                })->map(function ($participant) {
                    return $participant->user;
                })->values();
                
                $existingConversation->other_participants = $otherParticipants;
                
                // Remove the original participants to reduce response size
                unset($existingConversation->participants);
                
                return response()->json([
                    'status' => true,
                    'message' => 'Message sent to existing conversation',
                    'data' => [
                        'conversation' => $existingConversation,
                        'message' => $message
                    ]
                ], 200);
            }
        }
        
        // Create new conversation
        $conversation = new Conversation([
            'is_group' => $request->is_group ?? false,
            'group_name' => $request->group_name,
            'created_by' => $userId,
        ]);
        
        $conversation->save();
        
        // Add participants
        foreach ($participants as $participantId) {
            $conversation->participants()->create([
                'user_id' => $participantId,
            ]);
        }
        
        // Add first message
        $message = new Message([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'content' => $request->message,
            'is_read' => false,
        ]);
        
        $message->save();
        
        // Load the conversation with participants
        $conversation->load(['participants.user:id,name,profile_photo']);
        
        // Format participants to exclude current user
        $otherParticipants = $conversation->participants->filter(function ($participant) use ($userId) {
            return $participant->user_id != $userId;
        })->map(function ($participant) {
            return $participant->user;
        })->values();
        
        $conversation->other_participants = $otherParticipants;
        
        // Remove the original participants to reduce response size
        unset($conversation->participants);

        return response()->json([
            'status' => true,
            'message' => 'Conversation created successfully',
            'data' => [
                'conversation' => $conversation,
                'message' => $message
            ]
        ], 201);
    }

    /**
     * Display the specified conversation.
     */
    public function show($id)
    {
        $userId = Auth::id();
        
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->with(['participants.user:id,name,profile_photo'])
                               ->findOrFail($id);
        
        // Format participants to exclude current user
        $otherParticipants = $conversation->participants->filter(function ($participant) use ($userId) {
            return $participant->user_id != $userId;
        })->map(function ($participant) {
            return $participant->user;
        })->values();
        
        $conversation->other_participants = $otherParticipants;
        
        // Remove the original participants to reduce response size
        unset($conversation->participants);
        
        return response()->json([
            'status' => true,
            'message' => 'Conversation retrieved successfully',
            'data' => $conversation
        ], 200);
    }

    /**
     * Update the specified conversation in storage.
     */
    public function update(Request $request, $id)
    {
        $userId = Auth::id();
        
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($id);
        
        // Only group conversations can be updated
        if (!$conversation->is_group) {
            return response()->json([
                'status' => false,
                'message' => 'Only group conversations can be updated'
            ], 422);
        }
        
        // Only the creator can update the conversation
        if ($conversation->created_by !== $userId) {
            return response()->json([
                'status' => false,
                'message' => 'Only the group creator can update the conversation'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'group_name' => 'sometimes|string|max:255',
            'add_participants' => 'sometimes|array',
            'add_participants.*' => 'exists:users,id',
            'remove_participants' => 'sometimes|array',
            'remove_participants.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update group name if provided
        if ($request->has('group_name')) {
            $conversation->group_name = $request->group_name;
            $conversation->save();
        }
        
        // Add new participants if provided
        if ($request->has('add_participants')) {
            $existingParticipantIds = $conversation->participants->pluck('user_id')->toArray();
            
            foreach ($request->add_participants as $participantId) {
                if (!in_array($participantId, $existingParticipantIds)) {
                    $conversation->participants()->create([
                        'user_id' => $participantId,
                    ]);
                    
                    // Add system message about new participant
                    $user = User::find($participantId);
                    $systemMessage = new Message([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'content' => $user->name . ' has been added to the group',
                        'is_system_message' => true,
                        'is_read' => false,
                    ]);
                    
                    $systemMessage->save();
                }
            }
        }
        
        // Remove participants if provided
        if ($request->has('remove_participants')) {
            // Cannot remove the creator
            $removeParticipants = array_diff($request->remove_participants, [$conversation->created_by]);
            
            if (!empty($removeParticipants)) {
                $conversation->participants()
                            ->whereIn('user_id', $removeParticipants)
                            ->delete();
                
                // Add system message about removed participants
                foreach ($removeParticipants as $participantId) {
                    $user = User::find($participantId);
                    $systemMessage = new Message([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'content' => $user->name . ' has been removed from the group',
                        'is_system_message' => true,
                        'is_read' => false,
                    ]);
                    
                    $systemMessage->save();
                }
            }
        }
        
        // Reload the conversation with participants
        $conversation->load(['participants.user:id,name,profile_photo']);
        
        // Format participants to exclude current user
        $otherParticipants = $conversation->participants->filter(function ($participant) use ($userId) {
            return $participant->user_id != $userId;
        })->map(function ($participant) {
            return $participant->user;
        })->values();
        
        $conversation->other_participants = $otherParticipants;
        
        // Remove the original participants to reduce response size
        unset($conversation->participants);

        return response()->json([
            'status' => true,
            'message' => 'Conversation updated successfully',
            'data' => $conversation
        ], 200);
    }

    /**
     * Leave the specified conversation.
     */
    public function leaveConversation($id)
    {
        $userId = Auth::id();
        
        $conversation = Conversation::whereHas('participants', function ($query) use ($userId) {
                                   $query->where('user_id', $userId);
                               })
                               ->findOrFail($id);
        
        // If this is a one-on-one conversation, don't allow leaving
        if (!$conversation->is_group) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot leave a one-on-one conversation'
            ], 422);
        }
        
        // If this user is the creator and there are other participants, transfer ownership
        if ($conversation->created_by === $userId && $conversation->participants->count() > 1) {
            // Find another participant to transfer ownership to
            $newCreator = $conversation->participants()
                                     ->where('user_id', '!=', $userId)
                                     ->first();
            
            $conversation->created_by = $newCreator->user_id;
            $conversation->save();
            
            // Add system message about ownership transfer
            $newCreatorUser = User::find($newCreator->user_id);
            $systemMessage = new Message([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'content' => $newCreatorUser->name . ' is now the group admin',
                'is_system_message' => true,
                'is_read' => false,
            ]);
            
            $systemMessage->save();
        }
        
        // Remove the participant
        $conversation->participants()
                    ->where('user_id', $userId)
                    ->delete();
        
        // Add system message about leaving
        $user = Auth::user();
        $systemMessage = new Message([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'content' => $user->name . ' has left the group',
            'is_system_message' => true,
            'is_read' => false,
        ]);
        
        $systemMessage->save();
        
        // If no participants left, delete the conversation
        if ($conversation->participants()->count() === 0) {
            $conversation->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'You have left the conversation'
        ], 200);
    }
    
    /**
     * Find an existing one-on-one conversation between two users.
     */
    private function findExistingOneOnOneConversation($user1Id, $user2Id)
    {
        // Get all conversations where both users are participants
        $user1Conversations = Conversation::whereHas('participants', function ($query) use ($user1Id) {
                                        $query->where('user_id', $user1Id);
                                    })
                                    ->where('is_group', false)
                                    ->pluck('id');
        
        if ($user1Conversations->isEmpty()) {
            return null;
        }
        
        $conversation = Conversation::whereIn('id', $user1Conversations)
                                 ->whereHas('participants', function ($query) use ($user2Id) {
                                     $query->where('user_id', $user2Id);
                                 })
                                 ->where('is_group', false)
                                 ->first();
        
        return $conversation;
    }
}