<?php

namespace App\Containers\AppSection\WaterEntry\UI\API\Controllers;

use App\Containers\AppSection\WaterEntry\Actions\AddWaterAction;
use App\Containers\AppSection\WaterEntry\UI\API\Requests\AddWaterRequest;
use App\Containers\AppSection\WaterEntry\UI\API\Transformers\WaterEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class AddWaterController extends ApiController
{
    public function __invoke(AddWaterRequest $request, AddWaterAction $action): JsonResponse
    {
        $entry = $action->run($request->user(), $request->validated());

        return response()->json([
            'data' => (new WaterEntryTransformer())->transform($entry),
        ]);
    }
}
