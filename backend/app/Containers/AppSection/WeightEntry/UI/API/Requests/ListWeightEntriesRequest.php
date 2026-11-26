<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;

final class ListWeightEntriesRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', new CalendarDateRule()],
            'to' => ['nullable', 'string', new CalendarDateRule()],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.string' => 'Начальная дата должна быть календарной датой.',
            'to.string' => 'Конечная дата должна быть календарной датой.',
            'limit.integer' => 'Лимит должен быть целым числом.',
            'limit.min' => 'Лимит должен быть положительным.',
            'limit.max' => 'Слишком большой лимит.',
        ];
    }
}
