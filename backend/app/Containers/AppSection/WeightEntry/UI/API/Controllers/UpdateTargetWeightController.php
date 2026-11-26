<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Controllers;

use App\Containers\AppSection\WeightEntry\Actions\UpdateTargetWeightAction;
use App\Containers\AppSection\WeightEntry\UI\API\Requests\UpdateTargetWeightRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateTargetWeightController extends ApiController
{
    public function __invoke(
        UpdateTargetWeightRequest $request,
        UpdateTargetWeightAction $action,
    ): JsonResponse {
        $value = $request->validated('target_weight_kg');
        $profile = $action->run(
            $request->user(),
            null === $value ? null : (float) $value,
        );

        return response()->json([
            'data' => [
                'target_weight_kg' => null === $profile->target_weight_kg
                    ? null
                    : round((float) $profile->target_weight_kg, 3),
            ],
        ]);
    }
}
