<?php

namespace App\Containers\AppSection\Exercise\UI\API\Controllers;

use App\Containers\AppSection\Exercise\Actions\CreateExerciseAction;
use App\Containers\AppSection\Exercise\UI\API\Requests\CreateExerciseRequest;
use App\Containers\AppSection\Exercise\UI\API\Transformers\ExerciseTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateExerciseController extends ApiController
{
    public function __invoke(
        CreateExerciseRequest $request,
        CreateExerciseAction $action,
    ): JsonResponse {
        $exercise = $action->run($request->user(), $request->validated());

        return response()->json(
            ['data' => (new ExerciseTransformer())->transform($exercise)],
            201,
        );
    }
}
