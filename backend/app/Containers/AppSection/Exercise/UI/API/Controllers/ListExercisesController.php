<?php

namespace App\Containers\AppSection\Exercise\UI\API\Controllers;

use App\Containers\AppSection\Exercise\Actions\ListExercisesAction;
use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;
use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\Exercise\UI\API\Requests\ListExercisesRequest;
use App\Containers\AppSection\Exercise\UI\API\Transformers\ExerciseTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListExercisesController extends ApiController
{
    public function __invoke(
        ListExercisesRequest $request,
        ListExercisesAction $action,
    ): JsonResponse {
        $exercises = $action->run(
            $request->user(),
            $request->validated('search'),
            $request->validated('muscle_group'),
            $request->validated('equipment'),
        );

        return response()->json([
            'data' => array_values(array_map(
                static fn (Exercise $exercise): array => (new ExerciseTransformer())->transform($exercise),
                $exercises->all(),
            )),
            'meta' => [
                'filters' => [
                    'muscle_groups' => ExerciseCatalog::MUSCLE_GROUPS,
                    'equipment' => ExerciseCatalog::EQUIPMENT,
                    'measurement_types' => ExerciseCatalog::MEASUREMENT_TYPES,
                ],
            ],
        ]);
    }
}
