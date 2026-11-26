<?php

namespace App\Containers\AppSection\UserGoal\UI\API\Transformers;

use App\Containers\AppSection\UserGoal\Data\DTOs\NutritionCalculation;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class NutritionGoalTransformer extends ParentTransformer
{
    public function transform(UserGoal $goal): array
    {
        return [
            'id' => $goal->getHashedKey(),
            'effective_date' => $goal->effective_date->format('Y-m-d'),
            'timezone' => $goal->timezone,
            'weight_kg' => (float) $goal->weight_kg,
            'height_cm' => (float) $goal->height_cm,
            'birth_date' => $goal->birth_date->format('Y-m-d'),
            'fat_percentage' => is_null($goal->fat_percentage) ? null : (float) $goal->fat_percentage,
            'gender' => $goal->gender->value,
            'activity_level' => $goal->activity_level->value,
            'goal' => $goal->goal->value,
            'calories' => $goal->calories,
            'protein' => $goal->protein_grams,
            'fat' => $goal->fat_grams,
            'carbs' => $goal->carbs_grams,
            'explanation' => $this->explanationForSavedGoal($goal),
        ];
    }

    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string, timezone: string} $input
     */
    public function preview(array $input, NutritionCalculation $calculation): array
    {
        $input = NutritionGoalInputNormalizer::normalize($input);

        return [
            'timezone' => $input['timezone'],
            'weight_kg' => (float) $input['weight_kg'],
            'height_cm' => (float) $input['height_cm'],
            'birth_date' => $input['birth_date'],
            'fat_percentage' => is_null($input['fat_percentage'])
                ? null
                : (float) $input['fat_percentage'],
            'gender' => $input['gender'],
            'activity_level' => $input['activity_level'],
            'goal' => $input['goal'],
            'calories' => $calculation->calories,
            'protein' => $calculation->proteinGrams,
            'fat' => $calculation->fatGrams,
            'carbs' => $calculation->carbsGrams,
            'explanation' => $this->explanation($input, $calculation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function explanationForSavedGoal(UserGoal $goal): array
    {
        return $this->explanation([
            'weight_kg' => $goal->weight_kg,
            'height_cm' => $goal->height_cm,
            'birth_date' => $goal->birth_date->format('Y-m-d'),
            'fat_percentage' => $goal->fat_percentage,
            'gender' => $goal->gender->value,
            'activity_level' => $goal->activity_level->value,
            'goal' => $goal->goal->value,
            'timezone' => $goal->timezone,
        ], new NutritionCalculation(
            age: $goal->age,
            bmr: $goal->bmr,
            tdee: $goal->tdee,
            activityCoefficient: $goal->activity_coefficient,
            goalMultiplier: $goal->goal_multiplier,
            leanMassKg: $goal->lean_mass_kg,
            calories: $goal->calories,
            proteinGrams: $goal->protein_grams,
            fatGrams: $goal->fat_grams,
            carbsGrams: $goal->carbs_grams,
            usedLeanMass: !is_null($goal->lean_mass_kg),
        ));
    }

    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage: int|float|string|null, gender: string, activity_level: string, goal: string, timezone: string} $input
     *
     * @return array<string, mixed>
     */
    private function explanation(array $input, NutritionCalculation $calculation): array
    {
        $genderLabel = 'male' === (string) $input['gender'] ? 'для мужчин' : 'для женщин';
        $goalLabel = $this->goalLabel((string) $input['goal']);
        $activityLabel = $this->activityLabel((string) $input['activity_level']);

        $principles = [
            'Возраст на дату расчёта: ' . $calculation->age . ' лет.',
            'Основной обмен (BMR) рассчитан по формуле Миффлина — Сан Жеора ' . $genderLabel . ': '
                . $this->format($calculation->bmr) . ' ккал.',
            'Поддержание (TDEE) = BMR × коэффициент активности ' . $this->coefficient($calculation->activityCoefficient)
                . ' = ' . $this->format($calculation->tdee) . ' ккал.',
            'Поправка на цель «' . $goalLabel . '»: TDEE × ' . $this->coefficient($calculation->goalMultiplier)
                . '; калории округлены до ближайших 10 ккал и составили ' . $calculation->calories . ' ккал.',
        ];

        if ($calculation->usedLeanMass && !is_null($calculation->leanMassKg)) {
            $principles[] = 'Процент жира используется только для расчёта белков и не меняет BMR.';
            $principles[] = 'Безжировая масса = вес × (1 − процент жира / 100) = '
                . $this->format($calculation->leanMassKg) . ' кг. Белки = безжировая масса × 2 г/кг.';
        } else {
            $principles[] = 'Белки рассчитаны по весу: вес × 1.6 г/кг.';
        }

        $principles[] = 'Жиры = 25% целевых калорий ÷ 9.';
        $principles[] = 'Углеводы = остаток целевых калорий после белков и жиров ÷ 4. Белки и жиры сначала округлены до целых граммов.';

        return [
            'age' => $calculation->age,
            'bmr' => round($calculation->bmr, 2),
            'tdee' => round($calculation->tdee, 2),
            'activity_coefficient' => $calculation->activityCoefficient,
            'activity_label' => $activityLabel,
            'goal_multiplier' => $calculation->goalMultiplier,
            'goal_label' => $goalLabel,
            'lean_mass_kg' => is_null($calculation->leanMassKg) ? null : round($calculation->leanMassKg, 2),
            'protein_basis' => $calculation->usedLeanMass ? 'lean_mass' : 'body_weight',
            'principles' => $principles,
        ];
    }

    private function goalLabel(string $goal): string
    {
        return match ($goal) {
            'lose_weight' => 'Похудение',
            'gain_muscle' => 'Набор массы',
            default => $goal,
        };
    }

    private function activityLabel(string $activity): string
    {
        return match ($activity) {
            'sedentary' => 'В основном сижу',
            'light' => 'Лёгкая активность',
            'moderate' => 'Тренировки 3–5 раз в неделю',
            'high' => 'Высокая активность',
            default => $activity,
        };
    }

    private function coefficient(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', ''), '0'), ',');
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }
}
