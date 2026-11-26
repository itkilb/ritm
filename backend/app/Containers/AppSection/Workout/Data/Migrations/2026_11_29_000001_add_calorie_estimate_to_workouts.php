<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workouts', static function (Blueprint $table): void {
            $table->unsignedInteger('calories')->nullable()->default(null)->change();

            $table->string('calories_mode', 16)->nullable()->after('calories');
            $table->string('calories_status', 24)->nullable()->after('calories_mode');
            $table->string('calorie_method', 40)->nullable()->after('calories_status');
            $table->string('met_code', 16)->nullable()->after('calorie_method');
            $table->decimal('met_value', 5, 2)->nullable()->after('met_code');
            $table->string('met_label', 160)->nullable()->after('met_value');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('met_label');
            $table->string('weight_source', 32)->nullable()->after('weight_kg');
            $table->unsignedInteger('active_kcal')->nullable()->after('weight_source');
            $table->unsignedInteger('gross_kcal')->nullable()->after('active_kcal');
            $table->string('catalog_version', 16)->nullable()->after('gross_kcal');
            $table->string('formula_version', 16)->nullable()->after('catalog_version');
            $table->json('calorie_inputs')->nullable()->after('formula_version');
            $table->json('calorie_warnings')->nullable()->after('calorie_inputs');
            $table->boolean('calorie_approximate')->default(false)->after('calorie_warnings');
            $table->boolean('incline_ignored')->default(false)->after('calorie_approximate');
            $table->string('activity_kind', 32)->nullable()->after('incline_ignored');
            $table->string('strength_character', 24)->nullable()->after('activity_kind');
            $table->string('cardio_intensity', 16)->nullable()->after('strength_character');
            $table->unsignedSmallInteger('power_watts')->nullable()->after('cardio_intensity');
        });

        DB::table('workouts')
            ->whereNull('calories_mode')
            ->update([
                'calories_mode' => 'manual',
                'calories_status' => 'legacy',
            ]);
    }

    public function down(): void
    {
        DB::table('workouts')->whereNull('calories')->update(['calories' => 0]);

        Schema::table('workouts', static function (Blueprint $table): void {
            $table->unsignedInteger('calories')->nullable(false)->default(0)->change();

            $table->dropColumn([
                'calories_mode',
                'calories_status',
                'calorie_method',
                'met_code',
                'met_value',
                'met_label',
                'weight_kg',
                'weight_source',
                'active_kcal',
                'gross_kcal',
                'catalog_version',
                'formula_version',
                'calorie_inputs',
                'calorie_warnings',
                'calorie_approximate',
                'incline_ignored',
                'activity_kind',
                'strength_character',
                'cardio_intensity',
                'power_watts',
            ]);
        });
    }
};
