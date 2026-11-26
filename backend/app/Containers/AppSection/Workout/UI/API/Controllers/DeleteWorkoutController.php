<?php

namespace App\Containers\AppSection\Workout\UI\API\Controllers;

use App\Containers\AppSection\Workout\Actions\DeleteWorkoutAction;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\Response;

final class DeleteWorkoutController extends ApiController
{
    public function __invoke(
        string $id,
        DeleteWorkoutAction $action,
    ): Response {
        $action->run(request()->user(), $id);

        return response()->noContent();
    }
}
