<?php

namespace App\Containers\AppSection\Workout\Services;

/**
 * MET values and codes from the Compendium of Physical Activities 2024
 * (https://pacompendium.com/); fixed data, never interpolated.
 */
final class CalorieCatalog
{
    public const CATALOG_NAME = 'Compendium of Physical Activities 2024';
    public const CATALOG_VERSION = '2024/v1';
    public const FORMULA_VERSION = 'met-1-v1';

    public const RUNNING_URL = 'https://pacompendium.com/running/';
    public const BICYCLING_URL = 'https://pacompendium.com/bicycling/';
    public const CONDITIONING_URL = 'https://pacompendium.com/conditioning-exercise/';

    public const MPH_TO_KMH = 1.609344;

    private const EPSILON = 1.0e-9;

    /**
     * @return array<int, array{lower: float, upper: float, code: string, met: float, label: string, url: string}>
     */
    public function runningRanges(): array
    {
        return [
            $this->range(4.0, 4.2, '12028', 6.5, 'Бег 4,0–4,2 mph', self::RUNNING_URL),
            $this->range(4.3, 4.8, '12029', 7.8, 'Бег 4,3–4,8 mph', self::RUNNING_URL),
            $this->range(5.0, 5.2, '12030', 8.5, 'Бег 5,0–5,2 mph', self::RUNNING_URL),
            $this->range(5.5, 5.8, '12045', 9.0, 'Бег 5,5–5,8 mph', self::RUNNING_URL),
            $this->range(6.0, 6.3, '12050', 9.3, 'Бег 6,0–6,3 mph', self::RUNNING_URL),
            $this->range(6.7, 6.7, '12060', 10.5, 'Бег 6,7 mph', self::RUNNING_URL),
            $this->range(7.0, 7.0, '12070', 11.0, 'Бег 7,0 mph', self::RUNNING_URL),
            $this->range(7.5, 7.5, '12080', 11.8, 'Бег 7,5 mph', self::RUNNING_URL),
            $this->range(8.0, 8.0, '12090', 12.0, 'Бег 8,0 mph', self::RUNNING_URL),
            $this->range(8.6, 8.6, '12100', 12.5, 'Бег 8,6 mph', self::RUNNING_URL),
            $this->range(9.0, 9.0, '12110', 13.0, 'Бег 9,0 mph', self::RUNNING_URL),
            $this->range(9.3, 9.6, '12115', 14.8, 'Бег 9,3–9,6 mph', self::RUNNING_URL),
            $this->range(10.0, 10.0, '12120', 14.8, 'Бег 10,0 mph', self::RUNNING_URL),
            $this->range(11.0, 11.0, '12130', 16.8, 'Бег 11,0 mph', self::RUNNING_URL),
            $this->range(12.0, 12.0, '12132', 18.5, 'Бег 12,0 mph', self::RUNNING_URL),
            $this->range(13.0, 13.0, '12134', 19.8, 'Бег 13,0 mph', self::RUNNING_URL),
            $this->range(14.0, 14.0, '12135', 23.0, 'Бег 14,0 mph', self::RUNNING_URL),
        ];
    }

    /**
     * @return array<int, array{lower: float, upper: float, upper_exclusive?: bool, code: string, met: float, label: string, url: string}>
     */
    public function outdoorCyclingRanges(): array
    {
        return [
            $this->range(0.0, 10.0, '01010', 4.0, 'Велосипед на улице, менее 10 mph', self::BICYCLING_URL, true),
            $this->range(10.0, 11.9, '01020', 6.8, 'Велосипед на улице, 10–11,9 mph', self::BICYCLING_URL),
            $this->range(12.0, 13.9, '01030', 8.0, 'Велосипед на улице, 12–13,9 mph', self::BICYCLING_URL),
            $this->range(14.0, 15.9, '01040', 10.0, 'Велосипед на улице, 14–15,9 mph', self::BICYCLING_URL),
            $this->range(16.0, 19.0, '01050', 12.0, 'Велосипед на улице, 16–19 mph', self::BICYCLING_URL),
        ];
    }

    /**
     * @return array<int, array{lower: int, upper: int, code: string, met: float, label: string, url: string}>
     */
    public function stationaryCyclingRanges(): array
    {
        return [
            $this->range(25, 30, '01210', 3.5, 'Велотренажёр, 25–30 Вт', self::BICYCLING_URL),
            $this->range(50, 50, '01214', 4.0, 'Велотренажёр, 50 Вт', self::BICYCLING_URL),
            $this->range(60, 60, '01216', 5.0, 'Велотренажёр, 60 Вт', self::BICYCLING_URL),
            $this->range(70, 80, '01218', 5.8, 'Велотренажёр, 70–80 Вт', self::BICYCLING_URL),
            $this->range(90, 100, '01220', 6.0, 'Велотренажёр, 90–100 Вт', self::BICYCLING_URL),
            $this->range(101, 125, '01224', 6.8, 'Велотренажёр, 101–125 Вт', self::BICYCLING_URL),
            $this->range(126, 150, '01228', 8.0, 'Велотренажёр, 126–150 Вт', self::BICYCLING_URL),
            $this->range(151, 199, '01232', 10.3, 'Велотренажёр, 151–199 Вт', self::BICYCLING_URL),
            $this->range(200, 229, '01236', 10.8, 'Велотренажёр, 200–229 Вт', self::BICYCLING_URL),
            $this->range(230, 250, '01240', 12.5, 'Велотренажёр, 230–250 Вт', self::BICYCLING_URL),
            $this->range(270, 305, '01244', 13.8, 'Велотренажёр, 270–305 Вт', self::BICYCLING_URL),
        ];
    }

    /**
     * @return array{lower: int, upper: int, code: string, met: float, label: string, url: string}
     */
    public function stationaryGeneral(): array
    {
        return $this->range(0, 0, '01200', 6.8, 'Велотренажёр, общая тренировка', self::BICYCLING_URL);
    }

    /**
     * @return array<string, array{code: string, met: float, label: string, url: string}>
     */
    public function outdoorCyclingIntensity(): array
    {
        return [
            'easy' => $this->category('01015', 4.3, 'Велосипед на улице, лёгкая нагрузка', self::BICYCLING_URL),
            'moderate' => $this->category('01016', 7.0, 'Велосипед на улице, умеренная нагрузка', self::BICYCLING_URL),
            'vigorous' => $this->category('01017', 9.0, 'Велосипед на улице, интенсивная нагрузка', self::BICYCLING_URL),
        ];
    }

    /**
     * @return array<string, array{code: string, met: float, label: string, url: string}>
     */
    public function ellipticalIntensity(): array
    {
        return [
            'moderate' => $this->category('02048', 5.0, 'Эллипс, умеренная нагрузка', self::CONDITIONING_URL),
            'vigorous' => $this->category('02049', 9.0, 'Эллипс, интенсивная нагрузка', self::CONDITIONING_URL),
        ];
    }

    /**
     * @return array<string, array{code: string, met: float, label: string, url: string}>
     */
    public function strengthCharacter(): array
    {
        return [
            'ordinary' => $this->category('02054', 3.5, 'Силовая, обычная нагрузка', self::CONDITIONING_URL),
            'vigorous' => $this->category('02050', 6.0, 'Силовая, интенсивная нагрузка', self::CONDITIONING_URL),
            'circuit_moderate' => $this->category('02035', 5.0, 'Круговая тренировка, умеренная нагрузка', self::CONDITIONING_URL),
        ];
    }

    /**
     * @return array{entry: array<string, mixed>, approximate: bool}|null
     */
    public function selectRunning(float $speedMph): array|null
    {
        return $this->select($this->runningRanges(), $speedMph, 4.0, 14.0);
    }

    /**
     * @return array{entry: array<string, mixed>, approximate: bool}|null
     */
    public function selectOutdoorCycling(float $speedMph): array|null
    {
        return $this->select($this->outdoorCyclingRanges(), $speedMph, 0.0, 19.0);
    }

    /**
     * @return array{entry: array<string, mixed>, approximate: bool}|null
     */
    public function selectStationaryCycling(int $powerWatts): array|null
    {
        return $this->select($this->stationaryCyclingRanges(), $powerWatts, 25, 305);
    }

    public static function kmhToMph(float $speedKmh): float
    {
        return $speedKmh / self::MPH_TO_KMH;
    }

    /**
     * @param array<int, array<string, mixed>> $ranges
     *
     * @return array{entry: array<string, mixed>, approximate: bool}|null
     */
    private function select(array $ranges, int|float $value, int|float $minimum, int|float $maximum): array|null
    {
        if (!is_finite((float) $value)) {
            return null;
        }

        if ($value < $minimum - self::EPSILON || $value > $maximum + self::EPSILON) {
            return null;
        }

        foreach ($ranges as $entry) {
            if (!$this->within($entry, $value)) {
                continue;
            }

            return ['entry' => $entry, 'approximate' => false];
        }

        $best = null;
        $bestDistance = null;

        foreach ($ranges as $entry) {
            foreach (['lower', 'upper'] as $edge) {
                $boundary = (float) $entry[$edge];
                $distance = abs((float) $value - $boundary);

                if ($best === null || $distance < $bestDistance - self::EPSILON) {
                    $best = $entry;
                    $bestDistance = $distance;
                    continue;
                }

                if (abs($distance - (float) $bestDistance) > self::EPSILON) {
                    continue;
                }

                $best = $this->preferTie($best, $entry);
                $bestDistance = $distance;
            }
        }

        if (null === $best) {
            return null;
        }

        return ['entry' => $best, 'approximate' => true];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function within(array $entry, int|float $value): bool
    {
        $lower = (float) $entry['lower'];
        $upper = (float) $entry['upper'];

        if ($value < $lower - self::EPSILON) {
            return false;
        }

        if (!empty($entry['upper_exclusive'])) {
            return $value < $upper - self::EPSILON;
        }

        return $value <= $upper + self::EPSILON;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    private function preferTie(array $current, array $candidate): array
    {
        $currentMet = (float) $current['met'];
        $candidateMet = (float) $candidate['met'];

        if ($candidateMet < $currentMet - self::EPSILON) {
            return $candidate;
        }

        if (abs($candidateMet - $currentMet) > self::EPSILON) {
            return $current;
        }

        $currentLower = (float) $current['lower'];
        $candidateLower = (float) $candidate['lower'];

        if ($candidateLower < $currentLower - self::EPSILON) {
            return $candidate;
        }

        if (abs($candidateLower - $currentLower) <= self::EPSILON
            && strcmp((string) $candidate['code'], (string) $current['code']) < 0
        ) {
            return $candidate;
        }

        return $current;
    }

    /**
     * @return array{lower: int|float, upper: int|float, code: string, met: float, label: string, url: string, upper_exclusive?: bool}
     */
    private function range(
        int|float $lower,
        int|float $upper,
        string $code,
        float $met,
        string $label,
        string $url,
        bool $upperExclusive = false,
    ): array {
        $entry = [
            'lower' => $lower,
            'upper' => $upper,
            'code' => $code,
            'met' => $met,
            'label' => $label,
            'url' => $url,
        ];

        if ($upperExclusive) {
            $entry['upper_exclusive'] = true;
        }

        return $entry;
    }

    /**
     * @return array{code: string, met: float, label: string, url: string}
     */
    private function category(string $code, float $met, string $label, string $url): array
    {
        return ['code' => $code, 'met' => $met, 'label' => $label, 'url' => $url];
    }
}
