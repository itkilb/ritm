<?php

namespace App\Containers\AppSection\WeeklyStats\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;

final class GetWeeklyStatsRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'start' => ['nullable', 'string', new CalendarDateRule()],
        ];
    }

    public function messages(): array
    {
        return [
            'start.string' => 'Укажите дату начала в формате ГГГГ-ММ-ДД.',
        ];
    }
}
