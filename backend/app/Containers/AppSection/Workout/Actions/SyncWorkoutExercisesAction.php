<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\Exercise\Tasks\ResolveExerciseForUserTask;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Actions\Action as ParentAction;

final class SyncWorkoutExercisesAction extends ParentAction
{
    public function __construct(
        private readonly ResolveExerciseForUserTask $resolveExerciseForUserTask,
    ) {
    }

    /**
     * @param array<int, array{exercise_id: string|null, name: string, mode: string, position: int, sets: array<int, array{position: int, weight_kg: float|null, reps: int|null, seconds: int|null}>}> $exercises
     */
    public function run(User $user, Workout $workout, array $exercises): void
    {
        $workout->exercises()->delete();

        // Keep the snapshot name/mode even if the catalog row is later deleted.
        foreach ($exercises as $exerciseData) {
            $catalogExercise = null === $exerciseData['exercise_id']
                ? null
                : $this->resolveExerciseForUserTask->run($user, $exerciseData['exercise_id']);

            $exercise = $workout->exercises()->create([
                'exercise_id' => $catalogExercise?->getKey(),
                'name' => $exerciseData['name'],
                'mode' => $exerciseData['mode'],
                'position' => $exerciseData['position'],
            ]);

            foreach ($exerciseData['sets'] as $setData) {
                $exercise->sets()->create([
                    'position' => $setData['position'],
                    'weight_kg' => $setData['weight_kg'],
                    'reps' => $setData['reps'],
                    'seconds' => $setData['seconds'],
                ]);
            }
        }
    }
}
