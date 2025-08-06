<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'price',
        'category',
        'image',
        'is_available',
        'is_special',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_special' => 'boolean',
    ];

    /**
     * Get the restaurant this menu item belongs to
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
} 