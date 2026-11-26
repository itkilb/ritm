<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Controllers;

use App\Containers\AppSection\WeightEntry\Actions\CreateWeightEntryAction;
use App\Containers\AppSection\WeightEntry\UI\API\Requests\SaveWeightEntryRequest;
use App\Containers\AppSection\WeightEntry\UI\API\Transformers\WeightEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateWeightEntryController extends ApiController
{
    public function __invoke(
        SaveWeightEntryRequest $request,
        CreateWeightEntryAction $action,
    ): JsonResponse {
        $entry = $action->run($request->user(), $request->validated());

        return response()->json(
            ['data' => (new WeightEntryTransformer())->transform($entry)],
            201,
        );
    }
}
