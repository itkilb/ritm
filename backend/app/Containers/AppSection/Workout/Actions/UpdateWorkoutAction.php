<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Services\WorkoutCalorieService;
use App\Containers\AppSection\Workout\Services\WorkoutPayloadNormalizer;
use App\Containers\AppSection\Workout\Services\WorkoutSnapshotMapper;
use App\Containers\AppSection\Workout\Tasks\FindWorkoutForUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateWorkoutAction extends ParentAction
{
    public function __construct(
        private readonly FindWorkoutForUserTask $findWorkoutForUserTask,
        private readonly WorkoutPayloadNormalizer $normalizer,
        private readonly WorkoutCalorieService $calorieService,
        private readonly WorkoutSnapshotMapper $snapshotMapper,
        private readonly SyncWorkoutExercisesAction $syncExercises,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function run(User $user, string $hashedId, array $data): Workout
    {
        $normalized = $this->normalizer->normalize($data);

        return DB::transaction(function () use ($user, $hashedId, $normalized): Workout {
            $workout = $this->findWorkoutForUserTask->run($user, $hashedId, true);

            if (null === $workout) {
                abort(404, 'Тренировка не найдена.');
            }

            $calculation = $this->calorieService->calculate($user, $normalized, $workout);
            $columns = $this->snapshotMapper->columns($normalized, $calculation);

            $workout->update($columns);

            $this->syncExercises->run($user, $workout, $normalized['exercises']);

            return $workout->fresh(['exercises.exercise', 'exercises.sets']);
        });
    }
}
