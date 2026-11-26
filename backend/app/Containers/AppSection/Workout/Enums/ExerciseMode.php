<?php

namespace App\Containers\AppSection\Workout\Enums;

enum ExerciseMode: string
{
    case WEIGHTED = 'weighted';
    case REPS = 'reps';
    case TIME = 'time';
    case BODYWEIGHT = 'bodyweight';

    public function usesWeight(): bool
    {
        return self::WEIGHTED === $this || self::BODYWEIGHT === $this;
    }

    public function usesReps(): bool
    {
        return self::WEIGHTED === $this
            || self::BODYWEIGHT === $this
            || self::REPS === $this;
    }

    public function usesSeconds(): bool
    {
        return self::TIME === $this;
    }
}
