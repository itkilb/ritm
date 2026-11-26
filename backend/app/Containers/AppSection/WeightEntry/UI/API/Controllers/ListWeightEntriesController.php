<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Controllers;

use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserTimezoneTask;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\WeightEntry\Tasks\GetWeightEntriesTask;
use App\Containers\AppSection\WeightEntry\UI\API\Requests\ListWeightEntriesRequest;
use App\Containers\AppSection\WeightEntry\UI\API\Transformers\WeightEntryTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListWeightEntriesController extends ApiController
{
    public function __invoke(
        ListWeightEntriesRequest $request,
        GetWeightEntriesTask $getWeightEntriesTask,
        ResolveUserTimezoneTask $resolveUserTimezoneTask,
        ResolveUserLocalDateTask $resolveUserLocalDateTask,
    ): JsonResponse {
        $from = $request->validated('from');
        $to = $request->validated('to');
        $limit = (int) ($request->validated('limit') ?? 500);
        $entries = $getWeightEntriesTask->run(
            $request->user(),
            $from,
            $to,
            $limit,
        );

        $totalQuery = WeightEntry::query()->where('user_id', $request->user()->getKey());

        if (null !== $from) {
            $totalQuery->whereDate('date', '>=', $from);
        }

        if (null !== $to) {
            $totalQuery->whereDate('date', '<=', $to);
        }

        $total = $totalQuery->count();
        $returned = $entries->count();

        return response()->json([
            'data' => array_values(array_map(
                static fn (WeightEntry $entry): array => (new WeightEntryTransformer())->transform($entry),
                $entries->all(),
            )),
            'meta' => [
                'timezone' => $resolveUserTimezoneTask->run($request->user()),
                'today' => $resolveUserLocalDateTask->run($request->user()),
                'limit' => $limit,
                'returned' => $returned,
                'total' => $total,
                'has_more' => $total > $returned,
            ],
        ]);
    }
}
