<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Controllers;

use App\Containers\AppSection\FoodEntry\Actions\ChangeFoodEntryMealTypeAction;
use App\Containers\AppSection\FoodEntry\UI\API\Requests\ChangeFoodEntryMealTypeRequest;
use App\Containers\AppSection\FoodEntry\UI\API\Transformers\FoodEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChangeFoodEntryMealTypeController extends ApiController
{
    public function __invoke(
        ChangeFoodEntryMealTypeRequest $request,
        string $id,
        ChangeFoodEntryMealTypeAction $action,
    ): JsonResponse {
        if ('' === $id) {
            throw new NotFoundHttpException('Запись не найдена.');
        }

        $entry = $action->run(
            $request->user(),
            $id,
            (string) $request->validated('meal_type'),
        );

        return response()->json([
            'data' => (new FoodEntryTransformer())->transform($entry),
        ]);
    }
}
