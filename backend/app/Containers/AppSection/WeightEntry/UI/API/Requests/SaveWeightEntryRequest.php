<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\FiniteNumberRule;

final class SaveWeightEntryRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'date' => ['required', 'string', new CalendarDateRule()],
            'weight_kg' => [
                'required',
                'numeric',
                new FiniteNumberRule(),
                'min:35',
                'max:300',
                'decimal:0,3',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Укажите дату замера.',
            'date.string' => 'Укажите дату в формате ГГГГ-ММ-ДД.',
            'weight_kg.required' => 'Укажите вес.',
            'weight_kg.numeric' => 'Вес должен быть числом.',
            'weight_kg.min' => 'Вес должен быть не меньше 35 кг.',
            'weight_kg.max' => 'Вес не может превышать 300 кг.',
            'weight_kg.decimal' => 'Вес — не более трёх знаков после запятой.',
        ];
    }
}
