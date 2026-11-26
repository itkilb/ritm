<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\FiniteNumberRule;

final class UpdateTargetWeightRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'target_weight_kg' => [
                'present',
                'nullable',
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
            'target_weight_kg.present' => 'Передайте целевой вес или null, чтобы снять его.',
            'target_weight_kg.numeric' => 'Целевой вес должен быть числом.',
            'target_weight_kg.min' => 'Целевой вес должен быть не меньше 35 кг.',
            'target_weight_kg.max' => 'Целевой вес не может превышать 300 кг.',
            'target_weight_kg.decimal' => 'Целевой вес — не более трёх знаков после запятой.',
        ];
    }
}
