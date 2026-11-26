<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Requests;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class ChangeFoodEntryMealTypeRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'meal_type' => ['required', 'string', Rule::in(MealType::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'meal_type.required' => 'Выберите приём пищи.',
            'meal_type.string' => 'Приём пищи указан неверно.',
            'meal_type.in' => 'Выберите один из приёмов пищи: завтрак, обед, ужин или перекусы.',
        ];
    }
}
