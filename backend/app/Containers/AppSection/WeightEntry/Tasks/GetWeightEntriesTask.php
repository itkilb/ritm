<?php

namespace App\Containers\AppSection\WeightEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetWeightEntriesTask extends ParentTask
{
    /**
     * @return Collection<int, WeightEntry>
     */
    public function run(
        User $user,
        string|null $from = null,
        string|null $to = null,
        int|null $limit = 500,
    ): Collection {
        $query = WeightEntry::query()->where('user_id', $user->getKey());

        if (null !== $from) {
            $query->whereDate('date', '>=', $from);
        }

        if (null !== $to) {
            $query->whereDate('date', '<=', $to);
        }

        $query->orderBy('date')->orderBy('id');

        // null = all entries, used by progress for long histories.
        if (null !== $limit) {
            $query->limit(max(1, min($limit, 1000)));
        }

        return $query->get();
    }
}
