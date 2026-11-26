<?php

namespace App\Containers\AppSection\Authentication\UI\API\Controllers\Auth;

use App\Containers\AppSection\Authentication\Actions\Api\LoginAction;
use App\Containers\AppSection\Authentication\UI\API\Requests\Auth\LoginRequest;
use App\Containers\AppSection\User\UI\API\Transformers\CurrentUserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class LoginController extends ApiController
{
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $user = $action->run(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        return response()->json([
            'data' => (new CurrentUserTransformer())->transform($user),
        ]);
    }
}
