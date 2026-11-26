<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_profiles', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('weight_kg', 6, 2);
            $table->decimal('height_cm', 6, 2);
            $table->date('birth_date');
            $table->decimal('fat_percentage', 5, 2)->nullable();
            $table->string('gender', 16);
            $table->string('activity_level', 32);
            $table->string('goal', 32);
            $table->string('timezone', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
