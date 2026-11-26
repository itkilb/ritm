<?php

namespace App\Containers\AppSection\ActivityEntry\UI\API\Controllers;

use App\Containers\AppSection\ActivityEntry\Actions\CreateActivityEntryAction;
use App\Containers\AppSection\ActivityEntry\UI\API\Requests\CreateActivityEntryRequest;
use App\Containers\AppSection\ActivityEntry\UI\API\Transformers\ActivityEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateActivityEntryController extends ApiController
{
    public function __invoke(
        CreateActivityEntryRequest $request,
        CreateActivityEntryAction $action,
    ): JsonResponse {
        $entry = $action->run($request->user(), $request->validated());

        return response()->json([
            'data' => (new ActivityEntryTransformer())->transform($entry),
        ], 201);
    }
}
