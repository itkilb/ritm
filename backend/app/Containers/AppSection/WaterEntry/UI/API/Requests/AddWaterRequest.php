<?php

namespace App\Containers\AppSection\WaterEntry\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\StrictIntegerRule;

final class AddWaterRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'date' => ['required', 'string', new CalendarDateRule()],
            'amount_ml' => [
                'required',
                new StrictIntegerRule('Количество воды должно быть целым числом миллилитров.'),
                'integer',
                'min:1',
                'max:1000000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Укажите дату записи.',
            'amount_ml.required' => 'Укажите количество воды.',
            'amount_ml.integer' => 'Количество воды должно быть целым числом миллилитров.',
            'amount_ml.min' => 'Количество воды должно быть больше нуля.',
            'amount_ml.max' => 'Слишком большое количество воды за один раз.',
        ];
    }
}
