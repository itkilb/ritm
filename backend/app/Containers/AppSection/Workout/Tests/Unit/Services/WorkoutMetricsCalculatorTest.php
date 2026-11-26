<?php

namespace App\Containers\AppSection\Workout\Tests\Unit\Services;

use App\Containers\AppSection\Workout\Enums\ExerciseMode;
use App\Containers\AppSection\Workout\Services\WorkoutMetricsCalculator;
use App\Ship\Parents\Tests\TestCase as ParentTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WorkoutMetricsCalculatorTest extends ParentTestCase
{
    private WorkoutMetricsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WorkoutMetricsCalculator();
    }

    public function testSpeedAndPaceUseDocumentedRounding(): void
    {
        $metrics = $this->calculator->cardio(5.2, 1890);

        $this->assertSame(9.9, $metrics['speed_kmh']);
        $this->assertSame(363, $metrics['pace_seconds_per_km']);
        $this->assertSame('6:03', $metrics['pace']);
    }

    public function testDistanceFromSpeedIsRoundedToThreeDecimals(): void
    {
        $this->assertSame(4.95, $this->calculator->distanceFromSpeed(9.9, 1800));

        $this->assertSame(1.669, $this->calculator->distanceFromSpeed(10, 601));
    }

    public function testZeroAndInvalidInputsDoNotDivideByZero(): void
    {
        $this->assertNull($this->calculator->speedKmh(null, 600));
        $this->assertNull($this->calculator->speedKmh(0.0, 600));
        $this->assertNull($this->calculator->speedKmh(5.0, 0));
        $this->assertNull($this->calculator->speedKmh(NAN, 600));
        $this->assertNull($this->calculator->paceSecondsPerKm(0.0, 600));
        $this->assertNull($this->calculator->paceLabel(null));
        $this->assertNull($this->calculator->distanceFromSpeed(0, 600));
        $this->assertSame(
            ['speed_kmh' => null, 'pace_seconds_per_km' => null, 'pace' => null],
            $this->calculator->cardio(null, 600),
        );
    }

    public function testVolumeCountsOnlyWeightedAndBodyweightSets(): void
    {
        $exercises = [
            [
                'mode' => ExerciseMode::WEIGHTED,
                'sets' => [
                    ['weight_kg' => 60, 'reps' => 10],
                    ['weight_kg' => 62.5, 'reps' => 8],
                ],
            ],
            [
                'mode' => ExerciseMode::BODYWEIGHT,
                'sets' => [
                    ['weight_kg' => 10, 'reps' => 5],
                ],
            ],
            [
                'mode' => ExerciseMode::REPS,
                'sets' => [
                    ['weight_kg' => 100, 'reps' => 20],
                ],
            ],
            [
                'mode' => ExerciseMode::TIME,
                'sets' => [
                    ['seconds' => 60],
                ],
            ],
        ];

        $this->assertSame(1150.0, $this->calculator->volumeKg($exercises));
    }
}
