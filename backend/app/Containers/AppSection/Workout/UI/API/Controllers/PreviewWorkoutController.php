<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\PreviewWorkoutAction;
use App\Containers\AppSection\Workout\UI\API\Requests\PreviewWorkoutRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class PreviewWorkoutController extends ApiController
{
    public function __invoke(
        PreviewWorkoutRequest $request,
        PreviewWorkoutAction $action,
    ): JsonResponse {
        $summary = $action->run(
            $request->user(),
            $request->validated(),
            $request->validated('workout_id'),
        );

        return response()->json(['data' => $summary]);
    }
}
