<?php

namespace App\Containers\AppSection\FoodEntry\Actions;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateFoodEntryAction extends ParentAction
{
    /**
     * @param array{date: string, name: string, meal_type?: string|null, calories: int|float|string, protein: int|float|string, fat: int|float|string, carbs: int|float|string} $data
     */
    public function run(User $user, array $data): FoodEntry
    {
        return FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => $data['date'],
            'name' => trim((string) $data['name']),
            'meal_type' => MealType::normalize($data['meal_type'] ?? null)?->value,
            'calories' => $data['calories'],
            'protein' => $data['protein'],
            'fat' => $data['fat'],
            'carbs' => $data['carbs'],
        ]);
    }
}
