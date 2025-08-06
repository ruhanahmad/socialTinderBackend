<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPhotoController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\RestaurantReviewController;
use App\Http\Controllers\RestaurantSpecialController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTicketController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    
    // User management routes
    Route::get('/users', [UserController::class, 'getUsersByCountry']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/users/{userId}/like', [UserController::class, 'likeUser']);
    Route::delete('/users/{userId}/like', [UserController::class, 'removeLike']);
    Route::get('/my-likes', [UserController::class, 'getMyLikes']);
    Route::get('/countries', [UserController::class, 'getCountries']);
    
    // User filtering and dating routes
    Route::get('/users/filter', [UserController::class, 'filterUsers']);
    Route::get('/users/matches/potential', [UserController::class, 'getPotentialMatches']);
    
    // Social wall and friends routes
    Route::get('/social/wall', [UserController::class, 'getSocialWall']);
    Route::get('/users/friends', [UserController::class, 'getFriends']);
    Route::post('/users/friends', [UserController::class, 'addFriend']);
    Route::get('/users/friends/requests', [UserController::class, 'getPendingFriendRequests']);
    Route::post('/users/friends/requests/{userId}', [UserController::class, 'respondToFriendRequest']);
    
    // User photos routes
    Route::get('/photos', [UserPhotoController::class, 'index']);
    Route::get('/users/{userId}/photos', [UserPhotoController::class, 'index']);
    Route::post('/photos', [UserPhotoController::class, 'store']);
    Route::get('/photos/{id}', [UserPhotoController::class, 'show']);
    Route::put('/photos/{id}', [UserPhotoController::class, 'update']);
    Route::delete('/photos/{id}', [UserPhotoController::class, 'destroy']);
    Route::post('/photos/{id}/primary', [UserPhotoController::class, 'setPrimary']);
    Route::post('/photos/reorder', [UserPhotoController::class, 'reorder']);
    
    // Restaurant routes
    Route::apiResource('restaurants', RestaurantController::class);
    
    // Restaurant menu items routes
    Route::get('/restaurants/{restaurantId}/menu-items', [MenuItemController::class, 'index']);
    Route::post('/restaurants/{restaurantId}/menu-items', [MenuItemController::class, 'store']);
    Route::get('/restaurants/{restaurantId}/menu-items/{id}', [MenuItemController::class, 'show']);
    Route::put('/restaurants/{restaurantId}/menu-items/{id}', [MenuItemController::class, 'update']);
    Route::delete('/restaurants/{restaurantId}/menu-items/{id}', [MenuItemController::class, 'destroy']);
    
    // Restaurant reviews routes
    Route::get('/restaurants/{restaurantId}/reviews', [RestaurantReviewController::class, 'index']);
    Route::post('/restaurants/{restaurantId}/reviews', [RestaurantReviewController::class, 'store']);
    Route::get('/restaurants/{restaurantId}/reviews/{id}', [RestaurantReviewController::class, 'show']);
    Route::put('/restaurants/{restaurantId}/reviews/{id}', [RestaurantReviewController::class, 'update']);
    Route::delete('/restaurants/{restaurantId}/reviews/{id}', [RestaurantReviewController::class, 'destroy']);
    Route::get('/restaurants/{restaurantId}/my-review', [RestaurantReviewController::class, 'getUserReview']);
    
    // Restaurant specials routes
    Route::get('/restaurants/{restaurantId}/specials', [RestaurantSpecialController::class, 'index']);
    Route::post('/restaurants/{restaurantId}/specials', [RestaurantSpecialController::class, 'store']);
    Route::get('/restaurants/{restaurantId}/specials/{id}', [RestaurantSpecialController::class, 'show']);
    Route::put('/restaurants/{restaurantId}/specials/{id}', [RestaurantSpecialController::class, 'update']);
    Route::delete('/restaurants/{restaurantId}/specials/{id}', [RestaurantSpecialController::class, 'destroy']);
    Route::get('/specials/active', [RestaurantSpecialController::class, 'getActiveSpecials']);
    
    // Event routes
    Route::apiResource('events', EventController::class);
    Route::get('/events/featured', [EventController::class, 'getFeaturedEvents']);
    Route::get('/events/upcoming', [EventController::class, 'getUpcomingEvents']);
    Route::get('/events/categories', [EventController::class, 'getCategories']);
    
    // Event tickets routes
    Route::get('/events/{eventId}/tickets', [EventTicketController::class, 'index']);
    Route::post('/events/{eventId}/tickets', [EventTicketController::class, 'store']);
    Route::get('/events/{eventId}/tickets/{id}', [EventTicketController::class, 'show']);
    Route::put('/events/{eventId}/tickets/{id}', [EventTicketController::class, 'update']);
    Route::delete('/events/{eventId}/tickets/{id}', [EventTicketController::class, 'destroy']);
    Route::post('/events/{eventId}/tickets/{ticketId}/purchase', [EventTicketController::class, 'purchaseTickets']);
    Route::get('/my-tickets', [EventTicketController::class, 'getUserTickets']);
    
    // Post routes
    Route::apiResource('posts', PostController::class);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);
    Route::get('/posts/{id}/likes', [PostController::class, 'getLikes']);
    Route::get('/feed', [PostController::class, 'getFeed']);
    Route::get('/trending', [PostController::class, 'getTrending']);
    
    // Post comments routes
    Route::get('/posts/{postId}/comments', [PostCommentController::class, 'index']);
    Route::post('/posts/{postId}/comments', [PostCommentController::class, 'store']);
    Route::get('/posts/{postId}/comments/{id}', [PostCommentController::class, 'show']);
    Route::put('/posts/{postId}/comments/{id}', [PostCommentController::class, 'update']);
    Route::delete('/posts/{postId}/comments/{id}', [PostCommentController::class, 'destroy']);
    Route::get('/users/{userId?}/comments', [PostCommentController::class, 'getUserComments']);
    
    // Conversation routes
    Route::apiResource('conversations', ConversationController::class)->except(['destroy']);
    Route::post('/conversations/{id}/leave', [ConversationController::class, 'leaveConversation']);
    
    // Message routes
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store']);
    Route::get('/conversations/{conversationId}/messages/{id}', [MessageController::class, 'show']);
    Route::put('/conversations/{conversationId}/messages/{id}', [MessageController::class, 'update']);
    Route::delete('/conversations/{conversationId}/messages/{id}', [MessageController::class, 'destroy']);
    Route::post('/conversations/{conversationId}/read', [MessageController::class, 'markAsRead']);
    Route::get('/messages/unread', [MessageController::class, 'getUnreadCount']);
});