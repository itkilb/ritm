<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Controllers;

use App\Containers\AppSection\WeightEntry\Actions\DeleteWeightEntryAction;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteWeightEntryController extends ApiController
{
    public function __invoke(
        Request $request,
        DeleteWeightEntryAction $action,
        string $id,
    ): JsonResponse {
        $action->run($request->user(), $id);

        return response()->json(null, 204);
    }
}
