<?php

namespace App\Containers\AppSection\UserGoal\UI\API\Controllers;

use App\Containers\AppSection\UserGoal\Actions\GetCurrentNutritionGoalAction;
use App\Containers\AppSection\UserGoal\UI\API\Transformers\NutritionGoalTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCurrentNutritionGoalController extends ApiController
{
    public function __invoke(Request $request, GetCurrentNutritionGoalAction $action): JsonResponse
    {
        $goal = $action->run($request->user());

        return response()->json([
            'data' => $goal ? (new NutritionGoalTransformer())->transform($goal) : null,
        ]);
    }
}
