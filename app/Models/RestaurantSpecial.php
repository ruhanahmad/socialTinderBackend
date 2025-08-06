<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantSpecial extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'title',
        'description',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the restaurant this special belongs to
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
} 