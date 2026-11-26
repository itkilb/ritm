<?php

namespace App\Containers\AppSection\FoodEntry\Models;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FoodEntry extends ParentModel
{
    protected $fillable = [
        'user_id',
        'date',
        'name',
        'meal_type',
        'calories',
        'protein',
        'fat',
        'carbs',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'calories' => 'float',
        'protein' => 'float',
        'fat' => 'float',
        'carbs' => 'float',
    ];

    public function normalizedMealType(): MealType|null
    {
        return MealType::normalize($this->getAttribute('meal_type'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
