<?php

namespace App\Containers\AppSection\UserGoal\Services;

use App\Containers\AppSection\UserGoal\Data\DTOs\NutritionCalculation;
use App\Containers\AppSection\UserGoal\Exceptions\NutritionCalculationException;
use App\Containers\AppSection\UserProfile\Enums\ActivityLevel;
use App\Containers\AppSection\UserProfile\Enums\Gender;
use App\Containers\AppSection\UserProfile\Enums\Goal;
use Carbon\CarbonImmutable;

final class NutritionCalculator
{
    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string} $input
     */
    public function calculate(array $input, string $localDate): NutritionCalculation
    {
        $input = NutritionGoalInputNormalizer::normalize($input);

        $weightKg = (float) $input['weight_kg'];
        $heightCm = (float) $input['height_cm'];
        $birthDate = (string) $input['birth_date'];
        $fatPercentage = is_null($input['fat_percentage'])
            ? null
            : (float) $input['fat_percentage'];
        $gender = Gender::from((string) $input['gender']);
        $activity = ActivityLevel::from((string) $input['activity_level']);
        $goal = Goal::from((string) $input['goal']);

        $age = $this->ageOn($birthDate, $localDate);

        // Mifflin-St Jeor; full precision until final display rounding.
        $bmr = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;
        $bmr += Gender::MALE === $gender ? 5.0 : -161.0;

        $activityCoefficient = $activity->coefficient();
        $goalMultiplier = $goal->multiplier();
        $tdee = $bmr * $activityCoefficient;
        $targetCalories = $tdee * $goalMultiplier;

        // Nearest 10 kcal, halves up.
        $calories = (int) (round($targetCalories / 10, 0, PHP_ROUND_HALF_UP) * 10);

        if ($calories < 1200) {
            throw NutritionCalculationException::caloriesTooLow();
        }

        $leanMassKg = null;
        $usedLeanMass = !is_null($fatPercentage);

        if ($usedLeanMass) {
            $leanMassKg = $weightKg * (1 - $fatPercentage / 100);
            $proteinRaw = $leanMassKg * 2;
        } else {
            $proteinRaw = $weightKg * 1.6;
        }

        $proteinGrams = (int) round($proteinRaw, 0, PHP_ROUND_HALF_UP);
        $fatGrams = (int) round($calories * 0.25 / 9, 0, PHP_ROUND_HALF_UP);
        $carbsRaw = ($calories - $proteinGrams * 4 - $fatGrams * 9) / 4;

        if ($carbsRaw < 0) {
            throw NutritionCalculationException::negativeCarbs();
        }

        $carbsGrams = (int) round($carbsRaw, 0, PHP_ROUND_HALF_UP);

        return new NutritionCalculation(
            age: $age,
            bmr: $bmr,
            tdee: $tdee,
            activityCoefficient: $activityCoefficient,
            goalMultiplier: $goalMultiplier,
            leanMassKg: $leanMassKg,
            calories: $calories,
            proteinGrams: $proteinGrams,
            fatGrams: $fatGrams,
            carbsGrams: $carbsGrams,
            usedLeanMass: $usedLeanMass,
        );
    }

    public function ageOn(string $birthDate, string $localDate): int
    {
        $birth = CarbonImmutable::createFromFormat('!Y-m-d', $birthDate);
        $on = CarbonImmutable::createFromFormat('!Y-m-d', $localDate);

        if (false === $birth || false === $on) {
            throw new \InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD.');
        }

        $age = $on->year - $birth->year;

        if ($on->month < $birth->month || ($on->month === $birth->month && $on->day < $birth->day)) {
            --$age;
        }

        return $age;
    }
}
