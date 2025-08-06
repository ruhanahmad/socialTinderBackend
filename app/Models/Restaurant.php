<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'logo',
        'cover_photo',
        'cuisine_type',
        'price_range',
        'opening_hours',
        'is_active',
        'subscription_status',
        'subscription_expires_at',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'is_active' => 'boolean',
        'subscription_expires_at' => 'datetime',
    ];

    /**
     * Get restaurant's menu items
     */
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Get restaurant's specials
     */
    public function specials()
    {
        return $this->hasMany(RestaurantSpecial::class);
    }

    /**
     * Get restaurant's reviews
     */
    public function reviews()
    {
        return $this->hasMany(RestaurantReview::class);
    }

    /**
     * Calculate average rating
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * Get total reviews count
     */
    public function getReviewsCountAttribute()
    {
        return $this->reviews()->count();
    }
} 