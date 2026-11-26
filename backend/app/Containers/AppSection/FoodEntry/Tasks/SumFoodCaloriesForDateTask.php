<?php

namespace App\Containers\AppSection\FoodEntry\Tasks;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SumFoodCaloriesForDateTask extends ParentTask
{
    /**
     * @return array{eaten: float|null, has_entries: bool}
     */
    public function run(User $user, string $localDate): array
    {
        $row = FoodEntry::query()
            ->where('user_id', $user->getKey())
            ->where('date', $localDate)
            ->selectRaw('COUNT(*) AS entries_count, SUM(calories) AS calories_sum')
            ->first();

        $count = (int) ($row?->entries_count ?? 0);

        return [
            'eaten' => 0 === $count ? null : round((float) $row->calories_sum, 2),
            'has_entries' => $count > 0,
        ];
    }
}
