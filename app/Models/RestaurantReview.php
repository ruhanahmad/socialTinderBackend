<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'rating',
        'comment',
        'is_verified',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified' => 'boolean',
    ];

    /**
     * Get the restaurant this review belongs to
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the user who wrote this review
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 