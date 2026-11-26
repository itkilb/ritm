<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Controllers;

use App\Containers\AppSection\FoodEntry\Actions\DeleteFoodEntryAction;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteFoodEntryController extends ApiController
{
    public function __invoke(Request $request, string $id, DeleteFoodEntryAction $action): Response
    {
        if ('' === $id) {
            throw new NotFoundHttpException('Запись не найдена.');
        }

        $action->run($request->user(), $id);

        return response()->noContent();
    }
}
