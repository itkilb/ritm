<?php

namespace App\Containers\AppSection\ActivityEntry\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\FiniteNumberRule;
use App\Ship\Rules\StrictIntegerRule;

final class CreateActivityEntryRequest extends ParentRequest
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
                        $fail('Укажите название активности.');
                    }
                },
            ],
            'calories' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:1000000', 'decimal:0,2'],
            'steps' => [
                'required',
                new StrictIntegerRule('Количество шагов должно быть целым числом.'),
                'integer',
                'min:0',
                'max:1000000',
            ],
            'minutes' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:100000', 'decimal:0,2'],
            'distance_km' => ['required', 'numeric', new FiniteNumberRule(), 'min:0', 'max:99999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Укажите дату записи.',
            'name.required' => 'Укажите название активности.',
            'name.max' => 'Название не должно превышать 120 символов.',
            'calories.required' => 'Укажите потраченные калории.',
            'calories.numeric' => 'Калории должны быть числом.',
            'calories.min' => 'Калории не могут быть отрицательными.',
            'calories.decimal' => 'Калории — не более двух знаков после запятой.',
            'steps.required' => 'Укажите количество шагов.',
            'steps.integer' => 'Количество шагов должно быть целым числом.',
            'steps.min' => 'Количество шагов не может быть отрицательным.',
            'steps.max' => 'Слишком большое количество шагов.',
            'minutes.required' => 'Укажите минуты движения.',
            'minutes.numeric' => 'Минуты должны быть числом.',
            'minutes.min' => 'Минуты не могут быть отрицательными.',
            'minutes.decimal' => 'Минуты — не более двух знаков после запятой.',
            'distance_km.required' => 'Укажите расстояние.',
            'distance_km.numeric' => 'Расстояние должно быть числом.',
            'distance_km.min' => 'Расстояние не может быть отрицательным.',
            'distance_km.decimal' => 'Расстояние — не более трёх знаков после запятой.',
            'distance_km.max' => 'Слишком большое расстояние.',
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
