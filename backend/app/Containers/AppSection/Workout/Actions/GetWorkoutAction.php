<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Tasks\FindWorkoutForUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetWorkoutAction extends ParentAction
{
    public function __construct(
        private readonly FindWorkoutForUserTask $findWorkoutForUserTask,
    ) {
    }

    public function run(User $user, string $hashedId): Workout
    {
        $workout = $this->findWorkoutForUserTask->run($user, $hashedId);

        if (null === $workout) {
            abort(404, 'Тренировка не найдена.');
        }

        return $workout;
    }
}
