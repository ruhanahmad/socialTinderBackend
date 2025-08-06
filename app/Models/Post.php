<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'image',
        'location',
        'is_public',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the user who created the post
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get post likes
     */
    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * Get post comments
     */
    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * Check if a user has liked this post
     */
    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
} 