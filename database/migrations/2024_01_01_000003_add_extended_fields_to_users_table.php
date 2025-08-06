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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('age')->nullable()->after('description');
            $table->string('nationality')->nullable()->after('age');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('nationality');
            $table->string('height')->nullable()->after('gender');
            $table->json('interests')->nullable()->after('height');
            $table->string('location')->nullable()->after('interests');
            $table->decimal('latitude', 10, 8)->nullable()->after('location');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('username')->unique()->nullable()->after('longitude');
            $table->text('bio')->nullable()->after('username');
            $table->enum('relationship_status', ['single', 'in_relationship', 'married', 'complicated'])->nullable()->after('bio');
            $table->enum('looking_for', ['male', 'female', 'both'])->nullable()->after('relationship_status');
            $table->string('education')->nullable()->after('looking_for');
            $table->string('occupation')->nullable()->after('education');
            $table->string('instagram')->nullable()->after('occupation');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('twitter')->nullable()->after('facebook');
            $table->boolean('is_verified')->default(false)->after('twitter');
            $table->timestamp('last_active')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'age', 'nationality', 'gender', 'height', 'interests',
                'location', 'latitude', 'longitude', 'username', 'bio',
                'relationship_status', 'looking_for', 'education', 'occupation',
                'instagram', 'facebook', 'twitter', 'is_verified', 'last_active'
            ]);
        });
    }
}; 