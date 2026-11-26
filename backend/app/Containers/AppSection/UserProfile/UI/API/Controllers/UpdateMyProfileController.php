<?php

namespace App\Containers\AppSection\UserProfile\UI\API\Controllers;

use App\Containers\AppSection\User\UI\API\Transformers\CurrentUserTransformer;
use App\Containers\AppSection\UserGoal\Actions\SaveUserProfileAndGoalAction;
use App\Containers\AppSection\UserGoal\UI\API\Transformers\NutritionGoalTransformer;
use App\Containers\AppSection\UserProfile\UI\API\Requests\UpdateMyProfileRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateMyProfileController extends ApiController
{
    public function __invoke(
        UpdateMyProfileRequest $request,
        SaveUserProfileAndGoalAction $action,
    ): JsonResponse {
        $result = $action->run($request->user(), $request->validated());

        return response()->json([
            'data' => [
                'user' => (new CurrentUserTransformer())->transform($result['user']),
                'goal' => (new NutritionGoalTransformer())->transform($result['goal']),
            ],
        ]);
    }
}
