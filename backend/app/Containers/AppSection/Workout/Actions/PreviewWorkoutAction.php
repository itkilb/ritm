<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Services\WorkoutCalorieService;
use App\Containers\AppSection\Workout\Services\WorkoutPayloadNormalizer;
use App\Containers\AppSection\Workout\Tasks\FindWorkoutForUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class PreviewWorkoutAction extends ParentAction
{
    public function __construct(
        private readonly WorkoutPayloadNormalizer $normalizer,
        private readonly WorkoutCalorieService $calorieService,
        private readonly FindWorkoutForUserTask $findWorkoutForUserTask,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function run(User $user, array $data, string|null $workoutId = null): array
    {
        $normalized = $this->normalizer->normalize($data);
        $existing = null;

        if (null !== $workoutId && '' !== $workoutId) {
            $existing = $this->findWorkoutForUserTask->run($user, $workoutId);

            if (null === $existing) {
                abort(404, 'Тренировка не найдена.');
            }
        }

        $calculation = $this->calorieService->calculate($user, $normalized, $existing);

        return [
            'calculation' => $calculation->toArray(),
            'explanation' => $this->calorieService->explanation($calculation),
        ];
    }
}
