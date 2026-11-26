<?php

namespace App\Containers\AppSection\UserGoal\Services;

final class NutritionGoalInputNormalizer
{
    public const FAT_PERCENTAGE = 'fat_percentage';

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $fatPercentage = $input[self::FAT_PERCENTAGE] ?? null;

        if (is_string($fatPercentage)) {
            $fatPercentage = trim($fatPercentage);
            $fatPercentage = '' === $fatPercentage ? null : $fatPercentage;
        }

        $input[self::FAT_PERCENTAGE] = $fatPercentage;

        return $input;
    }
}
