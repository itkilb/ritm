<?php

namespace App\Containers\AppSection\Dashboard\UI\API\Controllers;

use App\Containers\AppSection\Dashboard\Actions\GetDashboardAction;
use App\Containers\AppSection\Dashboard\UI\API\Requests\GetDashboardRequest;
use App\Containers\AppSection\Dashboard\UI\API\Transformers\DashboardTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetDashboardController extends ApiController
{
    public function __invoke(
        GetDashboardRequest $request,
        GetDashboardAction $action,
    ): JsonResponse {
        $summary = $action->run($request->user(), $request->validated('date'));

        return response()->json([
            'data' => (new DashboardTransformer())->transform($summary),
        ]);
    }
}
