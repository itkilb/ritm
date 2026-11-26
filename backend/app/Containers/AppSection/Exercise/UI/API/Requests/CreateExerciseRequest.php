<?php

namespace App\Containers\AppSection\Exercise\UI\API\Requests;

use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class CreateExerciseRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || '' === trim($value)) {
                        $fail('Укажите название упражнения.');
                    }
                },
            ],
            'muscle_group' => ['required', 'string', Rule::in(ExerciseCatalog::MUSCLE_GROUPS)],
            'equipment' => ['required', 'string', Rule::in(ExerciseCatalog::EQUIPMENT)],
            'measurement_type' => [
                'required',
                'string',
                Rule::in(ExerciseCatalog::MEASUREMENT_TYPES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название упражнения.',
            'name.max' => 'Название не должно превышать 100 символов.',
            'muscle_group.required' => 'Выберите группу мышц.',
            'muscle_group.in' => 'Выберите группу мышц из списка.',
            'equipment.required' => 'Выберите оборудование.',
            'equipment.in' => 'Выберите оборудование из списка.',
            'measurement_type.required' => 'Выберите способ учёта.',
            'measurement_type.in' => 'Выберите способ учёта из списка.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
