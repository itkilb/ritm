<?php

namespace App\Containers\AppSection\Exercise\Actions;

use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CreateExerciseAction extends ParentAction
{
    /**
     * @param array{name: string, muscle_group: string, equipment: string, measurement_type: string} $data
     */
    public function run(User $user, array $data): Exercise
    {
        return DB::transaction(static fn (): Exercise => Exercise::query()->create([
            'user_id' => $user->getKey(),
            'slug' => null,
            'name' => trim((string) $data['name']),
            'muscle_group' => (string) $data['muscle_group'],
            'equipment' => (string) $data['equipment'],
            'measurement_type' => (string) $data['measurement_type'],
        ]));
    }
}
