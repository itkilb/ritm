<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('food_entries', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->date('date');
            $table->string('name', 120);

            $table->decimal('calories', 10, 2);
            $table->decimal('protein', 10, 2);
            $table->decimal('fat', 10, 2);
            $table->decimal('carbs', 10, 2);

            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_entries');
    }
};
