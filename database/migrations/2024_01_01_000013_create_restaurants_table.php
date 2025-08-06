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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('cuisine_type');
            $table->enum('price_range', ['$', '$$', '$$$', '$$$$'])->default('$$');
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('subscription_status', ['free', 'basic', 'premium'])->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
}; 