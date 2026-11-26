<?php

namespace App\Containers\AppSection\Progress\UI\API\Controllers;

use App\Containers\AppSection\Progress\Actions\GetProgressAction;
use App\Containers\AppSection\Progress\UI\API\Requests\GetProgressRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetProgressController extends ApiController
{
    public function __invoke(
        GetProgressRequest $request,
        GetProgressAction $action,
    ): JsonResponse {
        $summary = $action->run(
            $request->user(),
            $request->validated('period'),
            $request->validated('date'),
            $request->validated('exercise'),
        );

        return response()->json(['data' => $summary]);
    }
}
