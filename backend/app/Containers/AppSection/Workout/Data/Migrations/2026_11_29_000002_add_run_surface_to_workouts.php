<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workouts', static function (Blueprint $table): void {
            $table->string('run_surface', 24)->nullable()->after('incline_percent');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', static function (Blueprint $table): void {
            $table->dropColumn('run_surface');
        });
    }
};
