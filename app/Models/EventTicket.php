<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'ticket_number',
        'purchase_price',
        'purchase_date',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'datetime',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    /**
     * Get the event this ticket belongs to
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who purchased this ticket
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 