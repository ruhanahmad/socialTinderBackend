<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'phone_number',
        'profile_photo',
        'description',
        'age',
        'nationality',
        'gender',
        'height',
        'interests',
        'location',
        'latitude',
        'longitude',
        'username',
        'bio',
        'relationship_status',
        'looking_for',
        'education',
        'occupation',
        'instagram',
        'facebook',
        'twitter',
        'is_verified',
        'last_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'interests' => 'array',
        'last_active' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Get users that the current user has liked
     */
    public function likedUsers()
    {
        return $this->belongsToMany(User::class, 'user_likes', 'user_id', 'liked_user_id')
                    ->withTimestamps();
    }

    /**
     * Get users that have liked the current user
     */
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_likes', 'liked_user_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Check if current user has liked a specific user
     */
    public function hasLiked($userId)
    {
        return $this->likedUsers()->where('liked_user_id', $userId)->exists();
    }

    /**
     * Check if current user has been liked by a specific user
     */
    public function hasBeenLikedBy($userId)
    {
        return $this->likedByUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Get user's friends (mutual follows)
     */
    public function friends()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
                    ->wherePivot('status', 'accepted')
                    ->withTimestamps();
    }

    /**
     * Get user's followers
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
                    ->wherePivot('status', 'accepted')
                    ->withTimestamps();
    }

    /**
     * Get user's pending friend requests
     */
    public function pendingFriends()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
                    ->wherePivot('status', 'pending')
                    ->withTimestamps();
    }

    /**
     * Get user's posts
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get user's matches
     */
    public function matches()
    {
        return $this->belongsToMany(User::class, 'matches', 'user_id', 'matched_user_id')
                    ->withTimestamps();
    }

    /**
     * Calculate distance between two users
     */
    public function distanceTo($otherUser)
    {
        if (!$this->latitude || !$this->longitude || !$otherUser->latitude || !$otherUser->longitude) {
            return null;
        }

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($otherUser->latitude);
        $lon2 = deg2rad($otherUser->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return 6371 * $c; // Distance in kilometers
    }

    /**
     * Get user's photos
     */
    public function photos()
    {
        return $this->hasMany(UserPhoto::class);
    }

    /**
     * Get user's conversations
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
                    ->withTimestamps();
    }

    /**
     * Get user's messages
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
} 