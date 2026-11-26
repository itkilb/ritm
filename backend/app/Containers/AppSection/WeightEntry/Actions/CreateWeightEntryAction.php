<?php

namespace App\Containers\AppSection\WeightEntry\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\WeightEntry\Tasks\SyncProfileWeightFromHistoryTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWeightEntryAction extends ParentAction
{
    public function __construct(
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly SyncProfileWeightFromHistoryTask $syncProfileWeight,
    ) {
    }

    /**
     * @param array{date: string, weight_kg: int|float|string} $data
     */
    public function run(User $user, array $data): WeightEntry
    {
        $date = (string) $data['date'];
        $weight = (float) $data['weight_kg'];

        return DB::transaction(function () use ($user, $date, $weight): WeightEntry {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $today = $this->resolveUserLocalDateTask->run($user);

            if ($date > $today) {
                throw ValidationException::withMessages([
                    'date' => 'Нельзя записать вес на будущую дату.',
                ]);
            }

            $duplicate = WeightEntry::query()
                ->where('user_id', $user->getKey())
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'date' => 'Замер на эту дату уже есть. Отредактируйте существующий замер.',
                ]);
            }

            $entry = WeightEntry::query()->create([
                'user_id' => $user->getKey(),
                'date' => $date,
                'weight_kg' => $weight,
            ]);

            $this->syncProfileWeight->run($user);

            return $entry->fresh();
        }, 3);
    }
}
