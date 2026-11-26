<?php

namespace App\Containers\AppSection\Workout\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkoutSet extends ParentModel
{
    protected $fillable = [
        'workout_exercise_id',
        'position',
        'weight_kg',
        'reps',
        'seconds',
    ];

    protected $casts = [
        'position' => 'integer',
        'weight_kg' => 'float',
        'reps' => 'integer',
        'seconds' => 'integer',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class, 'workout_exercise_id');
    }
}
