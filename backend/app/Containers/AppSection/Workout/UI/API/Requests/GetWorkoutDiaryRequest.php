<?php

namespace App\Containers\AppSection\Workout\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;

final class GetWorkoutDiaryRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'string', new CalendarDateRule()],
        ];
    }

    public function messages(): array
    {
        return [
            'date.string' => 'Укажите дату в формате ГГГГ-ММ-ДД.',
        ];
    }
}
