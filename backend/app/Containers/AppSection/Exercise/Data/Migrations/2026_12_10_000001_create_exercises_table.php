<?php

use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exercises', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('slug', 80)->nullable()->unique();
            $table->string('name', 100);
            $table->string('muscle_group', 48);
            $table->string('equipment', 48);
            $table->string('measurement_type', 16);
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index('muscle_group');
            $table->index('equipment');
        });

        $now = now();
        $rows = array_map(
            static fn (array $exercise): array => array_merge($exercise, [
                'user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            ExerciseCatalog::exercises(),
        );

        DB::table('exercises')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
