<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('liked_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['like', 'dislike'])->default('like');
            $table->timestamps();
            
            // Prevent duplicate likes/dislikes from same user to same user
            $table->unique(['user_id', 'liked_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_likes');
    }
}; 