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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('matched_user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamp('matched_at')->useCurrent();
            $table->timestamps();
            
            // Prevent duplicate matches
            $table->unique(['user_id', 'matched_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
}; 