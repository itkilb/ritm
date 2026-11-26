<?php

namespace App\Containers\AppSection\UserGoal\UI\API\Controllers;

use App\Containers\AppSection\UserGoal\Actions\PreviewNutritionGoalAction;
use App\Containers\AppSection\UserGoal\UI\API\Requests\PreviewNutritionGoalRequest;
use App\Containers\AppSection\UserGoal\UI\API\Transformers\NutritionGoalTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class PreviewNutritionGoalController extends ApiController
{
    public function __invoke(
        PreviewNutritionGoalRequest $request,
        PreviewNutritionGoalAction $action,
    ): JsonResponse {
        $validated = $request->validated();
        $result = $action->run($validated);

        return response()->json([
            'data' => (new NutritionGoalTransformer())->preview($validated, $result['calculation']),
        ]);
    }
}
