<?php

namespace App\Containers\AppSection\Workout\Enums;

enum WorkoutType: string
{
    case STRENGTH = 'strength';
    case RUN = 'run';
    case CARDIO = 'cardio';

    public function isCardio(): bool
    {
        return self::RUN === $this || self::CARDIO === $this;
    }
}
