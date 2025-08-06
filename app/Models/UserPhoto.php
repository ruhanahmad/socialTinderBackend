<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'photo_path',
        'is_primary',
        'order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the user who owns this photo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 