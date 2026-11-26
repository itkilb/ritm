<?php

namespace App\Containers\AppSection\Workout\Models;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Enums\WorkoutFeeling;
use App\Containers\AppSection\Workout\Enums\WorkoutType;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Workout extends ParentModel
{
    protected $fillable = [
        'user_id',
        'date',
        'type',
        'name',
        'duration_seconds',
        'calories',
        'calories_mode',
        'calories_status',
        'calorie_method',
        'met_code',
        'met_value',
        'met_label',
        'weight_kg',
        'weight_kg_exact',
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
        'feeling',
        'note',
        'distance_km',
        'pulse',
        'incline_percent',
        'run_surface',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'type' => WorkoutType::class,
        'duration_seconds' => 'integer',
        'calories' => 'integer',
        'calories_mode' => 'string',
        'calories_status' => 'string',
        'calorie_method' => 'string',
        'met_code' => 'string',
        'met_value' => 'float',
        'met_label' => 'string',
        'weight_kg' => 'float',
        'weight_kg_exact' => 'float',
        'weight_source' => 'string',
        'active_kcal' => 'integer',
        'gross_kcal' => 'integer',
        'catalog_version' => 'string',
        'formula_version' => 'string',
        'calorie_inputs' => 'array',
        'calorie_warnings' => 'array',
        'calorie_approximate' => 'boolean',
        'incline_ignored' => 'boolean',
        'activity_kind' => 'string',
        'strength_character' => 'string',
        'cardio_intensity' => 'string',
        'power_watts' => 'integer',
        'feeling' => WorkoutFeeling::class,
        'distance_km' => 'float',
        'pulse' => 'integer',
        'incline_percent' => 'float',
        'run_surface' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('position');
    }

    // Prefer the exact snapshot; older rows fall back to the DECIMAL column.
    protected function weightKg(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): float|null {
                $exact = $this->attributes['weight_kg_exact'] ?? null;

                if (null !== $exact) {
                    return (float) $exact;
                }

                return null === $value ? null : (float) $value;
            },
        );
    }
}
