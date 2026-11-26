<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table): void {
            $table->double('weight_kg_exact')->nullable()->after('weight_kg');
            $table->double('height_cm_exact')->nullable()->after('height_cm');
            $table->double('fat_percentage_exact')->nullable()->after('fat_percentage');
        });

        Schema::table('user_goals', static function (Blueprint $table): void {
            $table->double('weight_kg_exact')->nullable()->after('weight_kg');
            $table->double('height_cm_exact')->nullable()->after('height_cm');
            $table->double('fat_percentage_exact')->nullable()->after('fat_percentage');
            $table->double('bmr_exact')->nullable()->after('bmr');
            $table->double('tdee_exact')->nullable()->after('tdee');
            $table->double('lean_mass_kg_exact')->nullable()->after('lean_mass_kg');
        });
    }

    public function down(): void
    {
        Schema::table('user_goals', static function (Blueprint $table): void {
            $table->dropColumn([
                'weight_kg_exact',
                'height_cm_exact',
                'fat_percentage_exact',
                'bmr_exact',
                'tdee_exact',
                'lean_mass_kg_exact',
            ]);
        });

        Schema::table('user_profiles', static function (Blueprint $table): void {
            $table->dropColumn([
                'weight_kg_exact',
                'height_cm_exact',
                'fat_percentage_exact',
            ]);
        });
    }
};
