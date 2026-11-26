<?php

namespace App\Containers\AppSection\UserProfile\Enums;

enum Goal: string
{
    case LOSE_WEIGHT = 'lose_weight';
    case GAIN_MUSCLE = 'gain_muscle';

    public function multiplier(): float
    {
        return match ($this) {
            self::LOSE_WEIGHT => 0.85,
            self::GAIN_MUSCLE => 1.10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOSE_WEIGHT => 'Похудение',
            self::GAIN_MUSCLE => 'Набор массы',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LOSE_WEIGHT => 'умеренный дефицит калорий',
            self::GAIN_MUSCLE => 'небольшой профицит калорий',
        };
    }
}
