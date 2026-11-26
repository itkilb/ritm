<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workouts', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->date('date');
            $table->string('type', 16);
            $table->string('name', 100);

            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('calories')->default(0);
            $table->string('feeling', 16)->nullable();
            $table->text('note')->nullable();

            $table->decimal('distance_km', 8, 3)->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->decimal('incline_percent', 5, 2)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'date', 'type']);
        });

        Schema::create('workout_exercises', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('mode', 16);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['workout_id', 'position']);
        });

        Schema::create('workout_sets', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')
                ->constrained('workout_exercises')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->decimal('weight_kg', 7, 2)->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedInteger('seconds')->nullable();

            $table->timestamps();

            $table->index(['workout_exercise_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workout_exercises');
        Schema::dropIfExists('workouts');
    }
};
