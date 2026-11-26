<?php

namespace App\Containers\AppSection\WeightEntry\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\WeightEntry\Tasks\FindWeightEntryForUserTask;
use App\Containers\AppSection\WeightEntry\Tasks\SyncProfileWeightFromHistoryTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateWeightEntryAction extends ParentAction
{
    public function __construct(
        private readonly FindWeightEntryForUserTask $findWeightEntryForUserTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly SyncProfileWeightFromHistoryTask $syncProfileWeight,
    ) {
    }

    /**
     * @param array{date: string, weight_kg: int|float|string} $data
     */
    public function run(User $user, string $hashedId, array $data): WeightEntry
    {
        $date = (string) $data['date'];
        $weight = (float) $data['weight_kg'];

        return DB::transaction(function () use ($user, $hashedId, $date, $weight): WeightEntry {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $entry = $this->findWeightEntryForUserTask->run($user, $hashedId, true);

            if (null === $entry) {
                abort(404, 'Замер не найден.');
            }

            $today = $this->resolveUserLocalDateTask->run($user);

            if ($date > $today) {
                throw ValidationException::withMessages([
                    'date' => 'Нельзя записать вес на будущую дату.',
                ]);
            }

            $duplicate = WeightEntry::query()
                ->where('user_id', $user->getKey())
                ->whereDate('date', $date)
                ->whereKeyNot($entry->getKey())
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'date' => 'Замер на эту дату уже есть. Отредактируйте существующий замер.',
                ]);
            }

            $entry->update([
                'date' => $date,
                'weight_kg' => $weight,
            ]);

            $this->syncProfileWeight->run($user);

            return $entry->fresh();
        }, 3);
    }
}
