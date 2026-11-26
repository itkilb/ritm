<?php

namespace App\Containers\AppSection\ActivityEntry\Actions;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateActivityEntryAction extends ParentAction
{
    /**
     * @param array{date: string, name: string, calories: int|float|string, steps: int|string, minutes: int|float|string, distance_km: int|float|string} $data
     */
    public function run(User $user, array $data): ActivityEntry
    {
        return ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => $data['date'],
            'name' => trim((string) $data['name']),
            'calories' => $data['calories'],
            'steps' => $data['steps'],
            'minutes' => $data['minutes'],
            'distance_km' => $data['distance_km'],
        ]);
    }
}
