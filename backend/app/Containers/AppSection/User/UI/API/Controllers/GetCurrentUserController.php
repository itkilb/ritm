<?php

namespace App\Containers\AppSection\User\UI\API\Controllers;

use App\Containers\AppSection\User\UI\API\Transformers\CurrentUserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCurrentUserController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('profile');

        return response()->json([
            'data' => (new CurrentUserTransformer())->transform($user),
        ]);
    }
}
