<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Controllers;

use App\Containers\AppSection\WeightEntry\Actions\UpdateWeightEntryAction;
use App\Containers\AppSection\WeightEntry\UI\API\Requests\SaveWeightEntryRequest;
use App\Containers\AppSection\WeightEntry\UI\API\Transformers\WeightEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateWeightEntryController extends ApiController
{
    public function __invoke(
        SaveWeightEntryRequest $request,
        UpdateWeightEntryAction $action,
        string $id,
    ): JsonResponse {
        $entry = $action->run($request->user(), $id, $request->validated());

        return response()->json(
            ['data' => (new WeightEntryTransformer())->transform($entry)],
        );
    }
}
