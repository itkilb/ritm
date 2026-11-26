<?php

namespace App\Containers\AppSection\UserProfile\Enums;

enum ActivityLevel: string
{
    case SEDENTARY = 'sedentary';
    case LIGHT = 'light';
    case MODERATE = 'moderate';
    case HIGH = 'high';

    public function coefficient(): float
    {
        return match ($this) {
            self::SEDENTARY => 1.2,
            self::LIGHT => 1.375,
            self::MODERATE => 1.55,
            self::HIGH => 1.725,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SEDENTARY => 'В основном сижу',
            self::LIGHT => 'Лёгкая активность',
            self::MODERATE => 'Тренировки 3–5 раз в неделю',
            self::HIGH => 'Высокая активность',
        };
    }
}
