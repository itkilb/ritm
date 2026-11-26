<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table): void {
            $table->decimal('target_weight_kg', 6, 3)->nullable()->after('weight_kg_exact');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table): void {
            $table->dropColumn('target_weight_kg');
        });
    }
};
