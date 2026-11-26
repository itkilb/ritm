<?php

namespace App\Containers\AppSection\UserProfile\Models;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Enums\ActivityLevel;
use App\Containers\AppSection\UserProfile\Enums\Gender;
use App\Containers\AppSection\UserProfile\Enums\Goal;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserProfile extends ParentModel
{
    protected $fillable = [
        'user_id',
        'weight_kg',
        'weight_kg_exact',
        'target_weight_kg',
        'height_cm',
        'height_cm_exact',
        'birth_date',
        'fat_percentage',
        'fat_percentage_exact',
        'gender',
        'activity_level',
        'goal',
        'timezone',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'weight_kg_exact' => 'float',
        'target_weight_kg' => 'float',
        'height_cm' => 'float',
        'height_cm_exact' => 'float',
        'birth_date' => 'immutable_date',
        'fat_percentage' => 'float',
        'fat_percentage_exact' => 'float',
        'gender' => Gender::class,
        'activity_level' => ActivityLevel::class,
        'goal' => Goal::class,
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
