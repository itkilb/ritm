<?php

namespace App\Containers\AppSection\Exercise\UI\API\Requests;

use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class ListExercisesRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'muscle_group' => ['nullable', 'string', Rule::in(ExerciseCatalog::MUSCLE_GROUPS)],
            'equipment' => ['nullable', 'string', Rule::in(ExerciseCatalog::EQUIPMENT)],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'Поисковый запрос слишком длинный.',
            'muscle_group.in' => 'Выберите группу мышц из списка.',
            'equipment.in' => 'Выберите оборудование из списка.',
        ];
    }
}
