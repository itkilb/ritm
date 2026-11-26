<?php

namespace App\Containers\AppSection\UserGoal\UI\API\Requests;

use App\Containers\AppSection\UserGoal\Services\NutritionCalculator;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Containers\AppSection\UserProfile\Enums\ActivityLevel;
use App\Containers\AppSection\UserProfile\Enums\Gender;
use App\Containers\AppSection\UserProfile\Enums\Goal;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class NutritionGoalRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'weight_kg' => ['required', 'numeric', 'between:35,300'],
            'height_cm' => ['required', 'numeric', 'between:120,230'],
            'birth_date' => ['required', 'string', 'date_format:Y-m-d'],
            'fat_percentage' => ['nullable', 'numeric', 'between:5,60'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'activity_level' => ['required', Rule::enum(ActivityLevel::class)],
            'goal' => ['required', Rule::enum(Goal::class)],
            'timezone' => ['required', 'string', 'max:64', 'timezone:ALL'],
        ];
    }

    public function messages(): array
    {
        return [
            'weight_kg.required' => 'Укажите вес.',
            'weight_kg.numeric' => 'Вес должен быть числом.',
            'weight_kg.between' => 'Вес должен быть от 35 до 300 кг.',
            'height_cm.required' => 'Укажите рост.',
            'height_cm.numeric' => 'Рост должен быть числом.',
            'height_cm.between' => 'Рост должен быть от 120 до 230 см.',
            'birth_date.required' => 'Укажите дату рождения.',
            'birth_date.date_format' => 'Дата рождения должна быть действительной датой в формате ГГГГ-ММ-ДД.',
            'fat_percentage.numeric' => 'Процент жира должен быть числом.',
            'fat_percentage.between' => 'Процент жира должен быть от 5 до 60.',
            'gender.required' => 'Выберите пол для расчёта.',
            'gender.enum' => 'Выберите корректный пол для расчёта.',
            'activity_level.required' => 'Выберите уровень активности.',
            'activity_level.enum' => 'Выберите корректный уровень активности.',
            'goal.required' => 'Выберите цель.',
            'goal.enum' => 'Выберите одну из двух целей: похудение или набор массы.',
            'timezone.required' => 'Не удалось определить часовой пояс. Обновите страницу.',
            'timezone.timezone' => 'Укажите корректный часовой пояс IANA.',
            'timezone.max' => 'Некорректный часовой пояс.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = NutritionGoalInputNormalizer::normalize($this->all());

        $this->merge([
            NutritionGoalInputNormalizer::FAT_PERCENTAGE => $normalized[NutritionGoalInputNormalizer::FAT_PERCENTAGE],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('birth_date') || $validator->errors()->has('timezone')) {
                return;
            }

            $birthDate = $this->input('birth_date');
            $timezone = $this->input('timezone');

            if (!is_string($birthDate) || !is_string($timezone)) {
                return;
            }

            try {
                $localDate = CarbonImmutable::now($timezone)->format('Y-m-d');
                $age = app(NutritionCalculator::class)->ageOn($birthDate, $localDate);
            } catch (\Throwable) {
                return;
            }

            if ($age < 18 || $age > 100) {
                $validator->errors()->add(
                    'birth_date',
                    'Возраст для расчёта должен быть от 18 до 100 лет.',
                );
            }
        });
    }
}
