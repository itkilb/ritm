<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\GetWorkoutDiaryAction;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\UI\API\Requests\GetWorkoutDiaryRequest;
use App\Containers\AppSection\Workout\UI\API\Transformers\WorkoutTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetWorkoutDiaryController extends ApiController
{
    public function __invoke(
        GetWorkoutDiaryRequest $request,
        GetWorkoutDiaryAction $action,
    ): JsonResponse {
        $summary = $action->run($request->user(), $request->validated('date'));
        $transformer = new WorkoutTransformer();

        return response()->json([
            'data' => [
                'date' => $summary['date'],
                'timezone' => $summary['timezone'],
                'today' => $summary['today'],
                'week_start' => $summary['week_start'],
                'week_end' => $summary['week_end'],
                'days' => $summary['days'],
                'week' => $summary['week'],
                'workouts' => array_values(array_map(
                    static fn (Workout $workout): array => $transformer->transform($workout),
                    $summary['workouts']->all(),
                )),
                'recent' => array_values(array_map(
                    static fn (Workout $workout): array => $transformer->transform($workout),
                    $summary['recent']->all(),
                )),
            ],
        ]);
    }
}
