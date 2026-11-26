<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\UpdateWorkoutAction;
use App\Containers\AppSection\Workout\UI\API\Requests\SaveWorkoutRequest;
use App\Containers\AppSection\Workout\UI\API\Transformers\WorkoutTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateWorkoutController extends ApiController
{
    public function __invoke(
        string $id,
        SaveWorkoutRequest $request,
        UpdateWorkoutAction $action,
    ): JsonResponse {
        $workout = $action->run($request->user(), $id, $request->validated());

        return response()->json([
            'data' => (new WorkoutTransformer())->transform($workout),
        ]);
    }
}
