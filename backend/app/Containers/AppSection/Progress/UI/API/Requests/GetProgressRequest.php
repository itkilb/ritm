<?php

namespace App\Containers\AppSection\Progress\UI\API\Requests;

use App\Containers\AppSection\Progress\Tasks\ResolveProgressPeriodTask;
use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use Illuminate\Validation\Rule;

final class GetProgressRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(ResolveProgressPeriodTask::PERIODS)],
            'date' => ['nullable', 'string', new CalendarDateRule()],
            'exercise' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'Выберите период: месяц, три месяца, год или всё время.',
            'date.string' => 'Укажите дату в формате ГГГГ-ММ-ДД.',
            'exercise.max' => 'Некорректный идентификатор упражнения.',
        ];
    }
}
