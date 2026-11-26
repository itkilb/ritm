<?php

namespace App\Containers\AppSection\Workout\Models;

use App\Containers\AppSection\Workout\Enums\ExerciseMode;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkoutExercise extends ParentModel
{
    protected $fillable = [
        'workout_id',
        'exercise_id',
        'name',
        'mode',
        'position',
    ];

    protected $casts = [
        'mode' => ExerciseMode::class,
        'position' => 'integer',
    ];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('position');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(\App\Containers\AppSection\Exercise\Models\Exercise::class);
    }
}
