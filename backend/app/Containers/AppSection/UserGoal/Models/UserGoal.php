<?php

namespace App\Containers\AppSection\UserGoal\Models;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Enums\ActivityLevel;
use App\Containers\AppSection\UserProfile\Enums\Gender;
use App\Containers\AppSection\UserProfile\Enums\Goal;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserGoal extends ParentModel
{
    protected $fillable = [
        'user_id',
        'effective_date',
        'timezone',
        'weight_kg',
        'weight_kg_exact',
        'height_cm',
        'height_cm_exact',
        'birth_date',
        'fat_percentage',
        'fat_percentage_exact',
        'gender',
        'activity_level',
        'goal',
        'age',
        'bmr',
        'bmr_exact',
        'tdee',
        'tdee_exact',
        'activity_coefficient',
        'goal_multiplier',
        'lean_mass_kg',
        'lean_mass_kg_exact',
        'calories',
        'protein_grams',
        'fat_grams',
        'carbs_grams',
    ];

    protected $casts = [
        'effective_date' => 'immutable_date',
        'birth_date' => 'immutable_date',
        'weight_kg' => 'float',
        'weight_kg_exact' => 'float',
        'height_cm' => 'float',
        'height_cm_exact' => 'float',
        'fat_percentage' => 'float',
        'fat_percentage_exact' => 'float',
        'gender' => Gender::class,
        'activity_level' => ActivityLevel::class,
        'goal' => Goal::class,
        'age' => 'integer',
        'bmr' => 'float',
        'bmr_exact' => 'float',
        'tdee' => 'float',
        'tdee_exact' => 'float',
        'activity_coefficient' => 'float',
        'goal_multiplier' => 'float',
        'lean_mass_kg' => 'float',
        'lean_mass_kg_exact' => 'float',
        'calories' => 'integer',
        'protein_grams' => 'integer',
        'fat_grams' => 'integer',
        'carbs_grams' => 'integer',
    ];

    // Exact float snapshots; older rows fall back to the DECIMAL columns.
    protected function weightKg(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): float => (float) ($this->attributes['weight_kg_exact'] ?? $value),
        );
    }

    protected function heightCm(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): float => (float) ($this->attributes['height_cm_exact'] ?? $value),
        );
    }

    protected function fatPercentage(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): float|null {
                $exact = $this->attributes['fat_percentage_exact'] ?? null;

                if (null !== $exact) {
                    return (float) $exact;
                }

                return null === $value ? null : (float) $value;
            },
        );
    }

    protected function bmr(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): float => (float) ($this->attributes['bmr_exact'] ?? $value),
        );
    }

    protected function tdee(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): float => (float) ($this->attributes['tdee_exact'] ?? $value),
        );
    }

    protected function leanMassKg(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): float|null {
                $exact = $this->attributes['lean_mass_kg_exact'] ?? null;

                if (null !== $exact) {
                    return (float) $exact;
                }

                return null === $value ? null : (float) $value;
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
