<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Tasks\FindWorkoutForUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class DeleteWorkoutAction extends ParentAction
{
    public function __construct(
        private readonly FindWorkoutForUserTask $findWorkoutForUserTask,
    ) {
    }

    public function run(User $user, string $hashedId): void
    {
        DB::transaction(function () use ($user, $hashedId): void {
            $workout = $this->findWorkoutForUserTask->run($user, $hashedId, true);

            if (null === $workout) {
                abort(404, 'Тренировка не найдена.');
            }

            $workout->delete();
        });
    }
}
