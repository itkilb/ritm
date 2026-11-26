<?php

namespace App\Containers\AppSection\WeeklyStats\UI\API\Controllers;

use App\Containers\AppSection\WeeklyStats\Actions\GetWeeklyStatsAction;
use App\Containers\AppSection\WeeklyStats\UI\API\Requests\GetWeeklyStatsRequest;
use App\Containers\AppSection\WeeklyStats\UI\API\Transformers\WeeklyStatsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetWeeklyStatsController extends ApiController
{
    public function __invoke(
        GetWeeklyStatsRequest $request,
        GetWeeklyStatsAction $action,
    ): JsonResponse {
        $summary = $action->run($request->user(), $request->validated('start'));

        return response()->json([
            'data' => (new WeeklyStatsTransformer())->transform($summary),
        ]);
    }
}
