<?php

namespace App\Containers\AppSection\Exercise\Actions;

use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class ListExercisesAction extends ParentAction
{
    /**
     * @return Collection<int, Exercise>
     */
    public function run(
        User $user,
        string|null $search = null,
        string|null $muscleGroup = null,
        string|null $equipment = null,
    ): Collection {
        $query = Exercise::query()->visibleTo($user);

        if (null !== $search && '' !== trim($search)) {
            $escaped = addcslashes(trim($search), '\\%_');
            $query->where('name', 'like', '%' . $escaped . '%');
        }

        if (null !== $muscleGroup && '' !== $muscleGroup) {
            $query->where('muscle_group', $muscleGroup);
        }

        if (null !== $equipment && '' !== $equipment) {
            $query->where('equipment', $equipment);
        }

        return $query
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }
}
