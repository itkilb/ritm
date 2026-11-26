<?php

namespace App\Containers\AppSection\Authentication\UI\API\Controllers\Auth;

use App\Containers\AppSection\Authentication\Actions\Api\RegisterAction;
use App\Containers\AppSection\Authentication\Tasks\AuthenticateUserTask;
use App\Containers\AppSection\Authentication\UI\API\Requests\Auth\RegisterRequest;
use App\Containers\AppSection\User\UI\API\Transformers\CurrentUserTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class RegisterController extends ApiController
{
    public function __invoke(
        RegisterRequest $request,
        RegisterAction $action,
        AuthenticateUserTask $authenticateUserTask,
    ): JsonResponse {
        $user = $action->run($request->validated());
        $authenticateUserTask->run($user);

        return response()->json([
            'data' => (new CurrentUserTransformer())->transform($user),
        ], 201);
    }
}
