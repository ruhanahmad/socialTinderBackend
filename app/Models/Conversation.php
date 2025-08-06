<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // 'direct', 'group', 'match'
        'last_message',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get users in this conversation
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
                    ->withTimestamps();
    }

    /**
     * Get messages in this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the last message
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }
} 