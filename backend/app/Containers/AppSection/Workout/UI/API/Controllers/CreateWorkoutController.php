<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\CreateWorkoutAction;
use App\Containers\AppSection\Workout\UI\API\Requests\SaveWorkoutRequest;
use App\Containers\AppSection\Workout\UI\API\Transformers\WorkoutTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateWorkoutController extends ApiController
{
    public function __invoke(
        SaveWorkoutRequest $request,
        CreateWorkoutAction $action,
    ): JsonResponse {
        $workout = $action->run($request->user(), $request->validated());

        return response()->json([
            'data' => (new WorkoutTransformer())->transform($workout),
        ], 201);
    }
}
