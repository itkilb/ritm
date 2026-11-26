<?php

namespace App\Containers\AppSection\UserGoal\Tests\Unit\Services;

use App\Containers\AppSection\UserGoal\Exceptions\NutritionCalculationException;
use App\Containers\AppSection\UserGoal\Services\NutritionCalculator;
use App\Containers\AppSection\UserGoal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NutritionCalculator::class)]
final class NutritionCalculatorTest extends UnitTestCase
{
    private NutritionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(NutritionCalculator::class);
    }

    public function testMaleLoseWeightWithoutFatPercentage(): void
    {
        $result = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $this->assertSame(35, $result->age);
        $this->assertEqualsWithDelta(1755.0, $result->bmr, 0.0001);
        $this->assertEqualsWithDelta(2720.25, $result->tdee, 0.0001);
        $this->assertSame(2310, $result->calories);
        $this->assertSame(128, $result->proteinGrams);
        $this->assertSame(64, $result->fatGrams);
        $this->assertSame(306, $result->carbsGrams);
        $this->assertNull($result->leanMassKg);
        $this->assertFalse($result->usedLeanMass);
    }

    public function testFemaleGainMuscleWithoutFatPercentage(): void
    {
        $result = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'female',
            'activity_level' => 'moderate',
            'goal' => 'gain_muscle',
        ]), '2025-01-15');

        $this->assertEqualsWithDelta(1589.0, $result->bmr, 0.0001);
        $this->assertEqualsWithDelta(2462.95, $result->tdee, 0.0001);
        $this->assertSame(2710, $result->calories);
        $this->assertSame(128, $result->proteinGrams);
        $this->assertSame(75, $result->fatGrams);
        $this->assertSame(381, $result->carbsGrams);
    }

    public function testFatPercentageUsesLeanMassForProteinButDoesNotChangeBmrOrCalories(): void
    {
        $withoutFat = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $withFat = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => 25,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $this->assertEqualsWithDelta($withoutFat->bmr, $withFat->bmr, 0.0001);
        $this->assertSame($withoutFat->calories, $withFat->calories);
        $this->assertEqualsWithDelta(60.0, (float) $withFat->leanMassKg, 0.0001);
        $this->assertSame(120, $withFat->proteinGrams);
        $this->assertSame(64, $withFat->fatGrams);
        $this->assertSame(314, $withFat->carbsGrams);
        $this->assertTrue($withFat->usedLeanMass);
    }

    public function testHighFatPercentageLowersProteinButNotBmr(): void
    {
        $result = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => 60,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $this->assertEqualsWithDelta(1755.0, $result->bmr, 0.0001);
        $this->assertEqualsWithDelta(32.0, (float) $result->leanMassKg, 0.0001);
        $this->assertSame(64, $result->proteinGrams);
    }

    public function testAgeOnDayBeforeBirthdayAndOnBirthday(): void
    {
        $this->assertSame(17, $this->calculator->ageOn('2007-06-15', '2025-06-14'));
        $this->assertSame(18, $this->calculator->ageOn('2007-06-15', '2025-06-15'));
        $this->assertSame(18, $this->calculator->ageOn('2007-06-15', '2025-06-16'));
    }

    public function testLeapDayBirthdayAcrossNonLeapYears(): void
    {
        $this->assertSame(24, $this->calculator->ageOn('2000-02-29', '2025-02-28'));
        $this->assertSame(25, $this->calculator->ageOn('2000-02-29', '2025-03-01'));
        $this->assertSame(25, $this->calculator->ageOn('2000-02-29', '2026-02-28'));
        $this->assertSame(26, $this->calculator->ageOn('2000-02-29', '2026-03-01'));
    }

    public function testMacroRoundingUsesRoundedProteinAndFatThenCarbs(): void
    {
        $result = $this->calculator->calculate($this->input([
            'weight_kg' => 95.3125,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $this->assertSame(153, $result->proteinGrams);
        $this->assertSame(0, $result->calories % 10);

        $expectedCarbs = (int) round(($result->calories - $result->proteinGrams * 4 - $result->fatGrams * 9) / 4, 0, PHP_ROUND_HALF_UP);
        $this->assertSame($expectedCarbs, $result->carbsGrams);
    }

    public function testCaloriesAreRoundedToNearestTenWithoutIntermediateRounding(): void
    {
        $result = $this->calculator->calculate($this->input([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ]), '2025-01-15');

        $rawTarget = (10 * 80 + 6.25 * 180 - 5 * 35 + 5) * 1.55 * 0.85;
        $this->assertSame(0, $result->calories % 10);
        $this->assertLessThanOrEqual(5, abs($rawTarget - $result->calories));
    }

    public function testImpossibleLowCalorieResultIsAnExplicitError(): void
    {
        $this->expectException(NutritionCalculationException::class);
        $this->expectExceptionMessage('ниже 1200 ккал');

        $this->calculator->calculate($this->input([
            'weight_kg' => 35,
            'height_cm' => 120,
            'birth_date' => '1925-01-15',
            'fat_percentage' => null,
            'gender' => 'female',
            'activity_level' => 'sedentary',
            'goal' => 'lose_weight',
        ]), '2025-01-15');
    }

    public function testNegativeCarbsAreAnExplicitErrorInsteadOfClamping(): void
    {
        $this->expectException(NutritionCalculationException::class);
        $this->expectExceptionMessage('углеводы получаются отрицательными');

        $this->calculator->calculate([
            'weight_kg' => 1000,
            'height_cm' => 120,
            'birth_date' => '1925-01-15',
            'fat_percentage' => 0,
            'gender' => 'male',
            'activity_level' => 'sedentary',
            'goal' => 'lose_weight',
        ], '2025-01-15');
    }

    public function testMissingFatPercentageKeyIsSafeAndUsesBodyWeight(): void
    {
        $result = $this->calculator->calculate([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ], '2025-01-15');

        $this->assertSame(2310, $result->calories);
        $this->assertSame(128, $result->proteinGrams);
        $this->assertNull($result->leanMassKg);
        $this->assertFalse($result->usedLeanMass);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides): array
    {
        return array_merge([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
        ], $overrides);
    }
}
