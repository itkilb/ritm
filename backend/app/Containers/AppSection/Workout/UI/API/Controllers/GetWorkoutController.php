<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\GetWorkoutAction;
use App\Containers\AppSection\Workout\UI\API\Transformers\WorkoutTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetWorkoutController extends ApiController
{
    public function __invoke(
        string $id,
        GetWorkoutAction $action,
    ): JsonResponse {
        $workout = $action->run(request()->user(), $id);

        return response()->json([
            'data' => (new WorkoutTransformer())->transform($workout),
        ]);
    }
}
