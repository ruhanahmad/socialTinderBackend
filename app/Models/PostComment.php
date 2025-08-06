<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'content',
        'parent_id',
    ];

    /**
     * Get the post this comment belongs to
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user who made this comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get parent comment (for replies)
     */
    public function parent()
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    /**
     * Get replies to this comment
     */
    public function replies()
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }
} 