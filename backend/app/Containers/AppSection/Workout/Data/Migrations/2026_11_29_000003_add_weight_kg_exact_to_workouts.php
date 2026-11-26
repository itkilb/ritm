<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive exact basis for new snapshots; existing rows stay unmodified.
        Schema::table('workouts', static function (Blueprint $table): void {
            $table->double('weight_kg_exact')->nullable()->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', static function (Blueprint $table): void {
            $table->dropColumn('weight_kg_exact');
        });
    }
};
