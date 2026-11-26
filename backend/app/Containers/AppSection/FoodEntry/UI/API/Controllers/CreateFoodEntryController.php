<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Controllers;

use App\Containers\AppSection\FoodEntry\Actions\CreateFoodEntryAction;
use App\Containers\AppSection\FoodEntry\UI\API\Requests\CreateFoodEntryRequest;
use App\Containers\AppSection\FoodEntry\UI\API\Transformers\FoodEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateFoodEntryController extends ApiController
{
    public function __invoke(
        CreateFoodEntryRequest $request,
        CreateFoodEntryAction $action,
    ): JsonResponse {
        $entry = $action->run($request->user(), $request->validated());

        return response()->json([
            'data' => (new FoodEntryTransformer())->transform($entry),
        ], 201);
    }
}
