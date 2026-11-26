<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('food_entries', static function (Blueprint $table) {
            $table->string('meal_type', 16)->nullable()->after('name');

            $table->index(['user_id', 'date', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::table('food_entries', static function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date', 'meal_type']);
            $table->dropColumn('meal_type');
        });
    }
};
