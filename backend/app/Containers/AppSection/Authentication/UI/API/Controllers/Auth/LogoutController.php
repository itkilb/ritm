<?php

namespace App\Containers\AppSection\Authentication\UI\API\Controllers\Auth;

use App\Containers\AppSection\Authentication\Actions\Api\LogoutAction;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\Response;

final class LogoutController extends ApiController
{
    public function __invoke(LogoutAction $action): Response
    {
        $action->run();

        return response()->noContent();
    }
}
