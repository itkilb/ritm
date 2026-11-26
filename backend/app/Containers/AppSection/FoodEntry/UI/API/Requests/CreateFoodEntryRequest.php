<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Requests;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\FiniteNumberRule;
use Illuminate\Validation\Rule;

final class CreateFoodEntryRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'date' => ['required', 'string', new CalendarDateRule()],
            'name' => [
                'required',
                'string',
                'max:120',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || '' === trim($value)) {
                        $fail('Укажите название приёма пищи.');
                    }
                },
            ],
            'meal_type' => ['nullable', 'string', Rule::in(MealType::values())],
            'calories' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:1000000', 'decimal:0,2'],
            'protein' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:1000000', 'decimal:0,2'],
            'fat' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:1000000', 'decimal:0,2'],
            'carbs' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Укажите дату записи.',
            'name.required' => 'Укажите название приёма пищи.',
            'name.max' => 'Название не должно превышать 120 символов.',
            'meal_type.string' => 'Приём пищи указан неверно.',
            'meal_type.in' => 'Выберите один из приёмов пищи: завтрак, обед, ужин или перекусы.',
            'calories.required' => 'Укажите калории.',
            'calories.numeric' => 'Калории должны быть числом.',
            'calories.min' => 'Калории не могут быть отрицательными.',
            'calories.decimal' => 'Калории — не более двух знаков после запятой.',
            'protein.required' => 'Укажите белки.',
            'protein.numeric' => 'Белки должны быть числом.',
            'protein.min' => 'Белки не могут быть отрицательными.',
            'protein.decimal' => 'Белки — не более двух знаков после запятой.',
            'fat.required' => 'Укажите жиры.',
            'fat.numeric' => 'Жиры должны быть числом.',
            'fat.min' => 'Жиры не могут быть отрицательными.',
            'fat.decimal' => 'Жиры — не более двух знаков после запятой.',
            'carbs.required' => 'Укажите углеводы.',
            'carbs.numeric' => 'Углеводы должны быть числом.',
            'carbs.min' => 'Углеводы не могут быть отрицательными.',
            'carbs.decimal' => 'Углеводы — не более двух знаков после запятой.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->rawInput();

        // Keep an explicitly empty string distinct from the legacy null value.
        if (array_key_exists('meal_type', $raw) && '' === $raw['meal_type']) {
            $this->merge(['meal_type' => '__invalid_empty_meal_type__']);
        }

        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rawInput(): array
    {
        $content = (string) $this->getContent();

        if ('' === $content) {
            return [];
        }

        if (str_contains((string) $this->header('CONTENT_TYPE'), 'application/json')) {
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        }

        $parsed = [];
        parse_str($content, $parsed);

        return $parsed;
    }
}
