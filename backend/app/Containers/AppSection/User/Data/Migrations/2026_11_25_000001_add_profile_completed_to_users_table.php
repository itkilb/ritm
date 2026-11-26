<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            /*
             * Authoritative server-side completion flag. It is deliberately not
             * fillable on the User model: task C must set it only after the
             * profile and goal have been persisted together.
             */
            $table->boolean('profile_completed')->default(false)->after('birth');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('profile_completed');
        });
    }
};
