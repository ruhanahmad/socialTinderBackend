<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'promoter_id',
        'title',
        'description',
        'image',
        'venue',
        'address',
        'latitude',
        'longitude',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'category',
        'ticket_price',
        'total_tickets',
        'available_tickets',
        'is_featured',
        'is_active',
        'commission_rate',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the promoter who created this event
     */
    public function promoter()
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    /**
     * Get event tickets
     */
    public function tickets()
    {
        return $this->hasMany(EventTicket::class);
    }

    /**
     * Get sold tickets count
     */
    public function getSoldTicketsCountAttribute()
    {
        return $this->total_tickets - $this->available_tickets;
    }

    /**
     * Calculate revenue
     */
    public function getRevenueAttribute()
    {
        return $this->sold_tickets_count * $this->ticket_price;
    }

    /**
     * Calculate platform commission
     */
    public function getCommissionAttribute()
    {
        return $this->revenue * ($this->commission_rate / 100);
    }
} 