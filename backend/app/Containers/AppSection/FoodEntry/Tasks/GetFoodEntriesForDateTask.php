<?php

namespace App\Containers\AppSection\FoodEntry\Tasks;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetFoodEntriesForDateTask extends ParentTask
{
    /**
     * @return Collection<int, FoodEntry>
     */
    public function run(User $user, string $localDate): Collection
    {
        return FoodEntry::query()
            ->where('user_id', $user->getKey())
            ->where('date', $localDate)
            ->orderBy('id')
            ->get();
    }
}
