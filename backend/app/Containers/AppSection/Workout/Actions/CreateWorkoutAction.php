<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Services\WorkoutCalorieService;
use App\Containers\AppSection\Workout\Services\WorkoutPayloadNormalizer;
use App\Containers\AppSection\Workout\Services\WorkoutSnapshotMapper;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CreateWorkoutAction extends ParentAction
{
    public function __construct(
        private readonly WorkoutPayloadNormalizer $normalizer,
        private readonly WorkoutCalorieService $calorieService,
        private readonly WorkoutSnapshotMapper $snapshotMapper,
        private readonly SyncWorkoutExercisesAction $syncExercises,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function run(User $user, array $data): Workout
    {
        $normalized = $this->normalizer->normalize($data);

        return DB::transaction(function () use ($user, $normalized): Workout {
            $calculation = $this->calorieService->calculate($user, $normalized);
            $columns = $this->snapshotMapper->columns($normalized, $calculation);

            /** @var Workout $workout */
            $workout = Workout::query()->create(array_merge($columns, [
                'user_id' => $user->getKey(),
            ]));

            $this->syncExercises->run($user, $workout, $normalized['exercises']);

            return $workout->fresh(['exercises.exercise', 'exercises.sets']);
        });
    }
}
