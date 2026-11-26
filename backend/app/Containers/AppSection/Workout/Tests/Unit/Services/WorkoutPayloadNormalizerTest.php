<?php

namespace App\Containers\AppSection\Workout\Tests\Unit\Services;

use App\Containers\AppSection\Workout\Services\WorkoutMetricsCalculator;
use App\Containers\AppSection\Workout\Services\WorkoutPayloadNormalizer;
use App\Ship\Parents\Tests\TestCase as ParentTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WorkoutPayloadNormalizerTest extends ParentTestCase
{
    public function testStrengthPayloadKeepsOrderedModesAndNullsIrrelevantFields(): void
    {
        $normalizer = new WorkoutPayloadNormalizer(new WorkoutMetricsCalculator());
        $normalized = $normalizer->normalize([
            'type' => 'strength',
            'date' => '2025-01-15',
            'name' => '  Верх тела  ',
            'duration_seconds' => 3300,
            'calories' => 300,
            'feeling' => 'comfortable',
            'note' => '  Хорошо  ',
            'exercises' => [
                [
                    'name' => ' Жим ',
                    'mode' => 'weighted',
                    'sets' => [
                        ['weight_kg' => '60.50', 'reps' => '10'],
                    ],
                ],
                [
                    'name' => 'Планка',
                    'mode' => 'time',
                    'sets' => [
                        ['seconds' => '45'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Верх тела', $normalized['name']);
        $this->assertSame('Хорошо', $normalized['note']);
        $this->assertNull($normalized['distance_km']);
        $this->assertNull($normalized['pulse']);
        $this->assertNull($normalized['incline_percent']);
        $this->assertCount(2, $normalized['exercises']);
        $this->assertSame('Жим', $normalized['exercises'][0]['name']);
        $this->assertSame(0, $normalized['exercises'][0]['position']);
        $this->assertSame(0, $normalized['exercises'][0]['sets'][0]['position']);
        $this->assertSame(60.5, $normalized['exercises'][0]['sets'][0]['weight_kg']);
        $this->assertNull($normalized['exercises'][0]['sets'][0]['seconds']);
        $this->assertNull($normalized['exercises'][1]['sets'][0]['weight_kg']);
    }

    public function testSpeedPayloadComputesDistanceAndDropsInclineForCardio(): void
    {
        $normalizer = new WorkoutPayloadNormalizer(new WorkoutMetricsCalculator());
        $normalized = $normalizer->normalize([
            'type' => 'cardio',
            'date' => '2025-01-15',
            'name' => 'Велосипед',
            'duration_seconds' => 1800,
            'calories' => 0,
            'feeling' => null,
            'input_mode' => 'speed',
            'speed_kmh' => 20,
            'pulse' => 140,
            'exercises' => [],
        ]);

        $this->assertSame(10.0, $normalized['distance_km']);
        $this->assertSame(140, $normalized['pulse']);
        $this->assertNull($normalized['incline_percent']);
        $this->assertSame([], $normalized['exercises']);
    }
}
