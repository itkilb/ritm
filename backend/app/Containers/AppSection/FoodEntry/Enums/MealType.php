<?php

namespace App\Containers\AppSection\FoodEntry\Enums;

enum MealType: string
{
    // null is not a case: legacy rows stay uncategorized until explicitly assigned.
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public static function normalize(mixed $value): self|null
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }
}
