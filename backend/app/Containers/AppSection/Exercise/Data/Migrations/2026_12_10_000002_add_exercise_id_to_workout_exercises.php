<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workout_exercises', static function (Blueprint $table): void {
            $table->foreignId('exercise_id')
                ->nullable()
                ->after('workout_id')
                ->constrained('exercises')
                ->nullOnDelete();

            $table->index(['exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exercise_id');
        });
    }
};
