<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_goals', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('timezone', 64);

            $table->decimal('weight_kg', 6, 2);
            $table->decimal('height_cm', 6, 2);
            $table->date('birth_date');
            $table->decimal('fat_percentage', 5, 2)->nullable();
            $table->string('gender', 16);
            $table->string('activity_level', 32);
            $table->string('goal', 32);

            $table->unsignedSmallInteger('age');
            $table->decimal('bmr', 10, 4);
            $table->decimal('tdee', 10, 4);
            $table->decimal('activity_coefficient', 5, 4);
            $table->decimal('goal_multiplier', 5, 4);
            $table->decimal('lean_mass_kg', 7, 3)->nullable();
            $table->unsignedInteger('calories');
            $table->unsignedInteger('protein_grams');
            $table->unsignedInteger('fat_grams');
            $table->unsignedInteger('carbs_grams');
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
            $table->index(['user_id', 'effective_date', 'id'], 'user_goals_current_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
    }
};
