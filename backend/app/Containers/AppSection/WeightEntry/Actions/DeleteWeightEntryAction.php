<?php

namespace App\Containers\AppSection\WeightEntry\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Tasks\FindWeightEntryForUserTask;
use App\Containers\AppSection\WeightEntry\Tasks\SyncProfileWeightFromHistoryTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class DeleteWeightEntryAction extends ParentAction
{
    public function __construct(
        private readonly FindWeightEntryForUserTask $findWeightEntryForUserTask,
        private readonly SyncProfileWeightFromHistoryTask $syncProfileWeight,
    ) {
    }

    public function run(User $user, string $hashedId): void
    {
        DB::transaction(function () use ($user, $hashedId): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $entry = $this->findWeightEntryForUserTask->run($user, $hashedId, true);

            if (null === $entry) {
                abort(404, 'Замер не найден.');
            }

            $entry->delete();

            $this->syncProfileWeight->run($user);
        }, 3);
    }
}
