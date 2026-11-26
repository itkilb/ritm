<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Data\CalorieCalculation;
use App\Containers\AppSection\Workout\Models\Workout;

final class WorkoutCalorieService
{
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';

    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_MANUAL = 'manual';
    public const STATUS_LEGACY = 'legacy';
    public const STATUS_MISSING = 'missing';
    public const STATUS_UNSUPPORTED = 'unsupported';

    public const MAX_ACTIVE_KCAL = 200_000;
    public const MAX_GROSS_KCAL = 200_000;

    private const EPSILON = 1.0e-9;

    public function __construct(
        private readonly CalorieCatalog $catalog,
        private readonly WorkoutCalorieFormula $formula,
        private readonly WorkoutCalorieWeightResolver $weightResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data normalized payload (see WorkoutPayloadNormalizer)
     */
    // Saved auto snapshots survive profile/catalog changes unless a calculation input changes.
    public function calculate(User $user, array $data, Workout|null $existing = null): CalorieCalculation
    {
        if (self::MODE_MANUAL === ($data['calories_mode'] ?? self::MODE_MANUAL)) {
            return $this->manualCalculation($data);
        }

        $explicitWeight = isset($data['calorie_weight_kg'])
            ? (float) $data['calorie_weight_kg']
            : null;
        $force = (bool) ($data['calories_recalculate'] ?? false);

        if (
            null !== $existing
            && !$force
            && null === $explicitWeight
            && self::MODE_AUTO === $existing->calories_mode
            && !$this->calculationInputsChanged($existing, $data)
        ) {
            return $this->fromWorkout($existing);
        }

        $weight = $this->weightResolver->resolve(
            $user,
            (string) $data['date'],
            $explicitWeight,
            $existing,
        );

        return $this->calculateAuto($data, $weight);
    }

    public function fromWorkout(Workout $workout): CalorieCalculation
    {
        $mode = self::MODE_AUTO === $workout->calories_mode
            ? self::MODE_AUTO
            : self::MODE_MANUAL;
        $status = $workout->calories_status
            ?? (self::MODE_AUTO === $mode ? self::STATUS_MISSING : self::STATUS_LEGACY);

        return new CalorieCalculation(
            mode: $mode,
            status: $status,
            calories: null === $workout->calories ? null : (int) $workout->calories,
            activeKcal: null === $workout->active_kcal ? null : (int) $workout->active_kcal,
            grossKcal: null === $workout->gross_kcal ? null : (int) $workout->gross_kcal,
            method: $workout->calorie_method,
            metCode: $workout->met_code,
            metValue: null === $workout->met_value ? null : (float) $workout->met_value,
            metLabel: $workout->met_label,
            weightKg: null === $workout->weight_kg ? null : (float) $workout->weight_kg,
            weightSource: $workout->weight_source,
            inputs: is_array($workout->calorie_inputs) ? $workout->calorie_inputs : [],
            warnings: is_array($workout->calorie_warnings) ? $workout->calorie_warnings : [],
            approximate: (bool) $workout->calorie_approximate,
            inclineIgnored: (bool) $workout->incline_ignored,
            catalogVersion: $workout->catalog_version,
            formulaVersion: $workout->formula_version,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function explanation(CalorieCalculation $calculation): array
    {
        $inputs = $calculation->inputs;
        $reason = isset($inputs['reason']) && is_string($inputs['reason'])
            ? $inputs['reason']
            : null;
        $notes = isset($inputs['notes']) && is_array($inputs['notes'])
            ? array_values(array_filter($inputs['notes'], 'is_string'))
            : [];

        return [
            'mode' => $calculation->mode,
            'status' => $calculation->status,
            'active_kcal' => $calculation->activeKcal,
            'gross_kcal' => $calculation->grossKcal,
            'met' => $calculation->metValue,
            'met_code' => $calculation->metCode,
            'category' => $calculation->metLabel
                ?? (isset($inputs['category_label']) && is_string($inputs['category_label'])
                    ? $inputs['category_label']
                    : null),
            'weight_kg' => $calculation->weightKg,
            'weight_source' => $calculation->weightSource,
            'duration_seconds' => isset($inputs['duration_seconds'])
                ? (int) $inputs['duration_seconds']
                : null,
            'catalog' => CalorieCatalog::CATALOG_NAME,
            'catalog_version' => $calculation->catalogVersion,
            'formula_version' => $calculation->formulaVersion,
            'catalog_url' => isset($inputs['catalog_url']) && is_string($inputs['catalog_url'])
                ? $inputs['catalog_url']
                : null,
            'approximate' => $calculation->approximate,
            'incline_ignored' => $calculation->inclineIgnored,
            'reason' => $reason,
            'warnings' => $calculation->warnings,
            'notes' => $notes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function manualCalculation(array $data): CalorieCalculation
    {
        $legacy = (bool) ($data['legacy_calories'] ?? false);
        $calories = null === ($data['calories'] ?? null) ? null : (int) $data['calories'];

        return new CalorieCalculation(
            mode: self::MODE_MANUAL,
            status: $legacy ? self::STATUS_LEGACY : self::STATUS_MANUAL,
            calories: $calories,
            activeKcal: null,
            grossKcal: null,
            method: 'manual',
            metCode: null,
            metValue: null,
            metLabel: null,
            weightKg: null,
            weightSource: null,
            inputs: [],
            warnings: [],
            approximate: false,
            inclineIgnored: false,
            catalogVersion: null,
            formulaVersion: null,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function calculateAuto(array $data, array $weight): CalorieCalculation
    {
        $type = (string) $data['type'];
        $duration = (int) $data['duration_seconds'];
        $distance = null === ($data['distance_km'] ?? null) ? null : (float) $data['distance_km'];
        $incline = null === ($data['incline_percent'] ?? null) ? null : (float) $data['incline_percent'];
        $inclineIgnored = (bool) ($data['incline_ignored'] ?? false);
        $speedKmh = null;

        if (null !== $distance && $distance > 0 && $duration > 0) {
            $speedKmh = $distance / ($duration / 3600);
        }

        $inputs = [
            'duration_seconds' => $duration,
            'distance_km' => $distance,
            'speed_kmh' => null === $speedKmh ? null : round($speedKmh, 4),
            'incline_percent' => $incline,
            'incline_ignored' => $inclineIgnored,
            'run_surface' => $data['run_surface'] ?? null,
            'strength_character' => $data['strength_character'] ?? null,
            'activity_kind' => $data['activity_kind'] ?? null,
            'cardio_intensity' => $data['cardio_intensity'] ?? null,
            'power_watts' => isset($data['power_watts']) ? (int) $data['power_watts'] : null,
            'notes' => [],
        ];

        $warnings = [];

        if (null !== $weight['warning']) {
            $warnings[] = $weight['warning'];
        }

        if (null === $weight['weight_kg']) {
            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_weight',
                'Не удалось определить вес для расчёта. Укажите вес вручную.',
                $inputs,
                $warnings,
                $weight,
                $inclineIgnored,
            );
        }

        if ('strength' === $type) {
            return $this->calculateStrength($data, $inputs, $warnings, $weight, $duration);
        }

        if ('run' === $type) {
            return $this->calculateRun(
                $data,
                $inputs,
                $warnings,
                $weight,
                $duration,
                $speedKmh,
                $incline,
                $inclineIgnored,
            );
        }

        return $this->calculateCardio(
            $data,
            $inputs,
            $warnings,
            $weight,
            $duration,
            $speedKmh,
            $inclineIgnored,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function calculateStrength(
        array $data,
        array $inputs,
        array $warnings,
        array $weight,
        int $duration,
    ): CalorieCalculation {
        $character = isset($data['strength_character']) && is_string($data['strength_character'])
            ? $data['strength_character']
            : null;
        $categories = $this->catalog->strengthCharacter();

        if (null === $character || !isset($categories[$character])) {
            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_strength_character',
                'Выберите характер силовой тренировки, чтобы рассчитать калории.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        return $this->complete(
            $categories[$character],
            'strength_character',
            $weight,
            $duration,
            $inputs,
            $warnings,
            false,
            false,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function calculateRun(
        array $data,
        array $inputs,
        array $warnings,
        array $weight,
        int $duration,
        float|null $speedKmh,
        float|null $incline,
        bool $inclineIgnored,
    ): CalorieCalculation {
        if (null !== $incline && $incline > 0 && !$inclineIgnored) {
            return $this->incomplete(
                self::STATUS_UNSUPPORTED,
                'unsupported_incline',
                'Расчёт с уклоном пока не поддерживается. Укажите калории вручную или подтвердите оценку без учёта уклона.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        if (null === $speedKmh || $speedKmh <= 0) {
            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_run_distance',
                'Укажите дистанцию или скорость, чтобы рассчитать калории.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        $selection = $this->catalog->selectRunning(CalorieCatalog::kmhToMph($speedKmh));

        if (null === $selection) {
            return $this->incomplete(
                self::STATUS_UNSUPPORTED,
                'unsupported_run_speed',
                'Скорость вне поддерживаемого диапазона 4,0–14,0 mph — автоматический расчёт недоступен.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        if (null !== $incline && $incline > 0 && $inclineIgnored) {
            $warnings[] = 'Уклон не учтён: оценка дана для ровной поверхности.';
        }

        $warnings[] = 'Оценка по средней скорости: интервалы отдельно не учитываются.';
        $warnings[] = 'Пульс не корректирует MET.';

        return $this->complete(
            $selection['entry'],
            'run_speed',
            $weight,
            $duration,
            $inputs,
            $warnings,
            $selection['approximate'],
            $inclineIgnored,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function calculateCardio(
        array $data,
        array $inputs,
        array $warnings,
        array $weight,
        int $duration,
        float|null $speedKmh,
        bool $inclineIgnored,
    ): CalorieCalculation {
        $kind = isset($data['activity_kind']) && is_string($data['activity_kind'])
            ? $data['activity_kind']
            : null;
        $intensity = isset($data['cardio_intensity']) && is_string($data['cardio_intensity'])
            ? $data['cardio_intensity']
            : null;
        $power = isset($data['power_watts']) ? (int) $data['power_watts'] : null;

        if (null === $kind) {
            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_activity_kind',
                'Выберите вид кардиотренировки, чтобы рассчитать калории.',
                $inputs,
                $warnings,
                $weight,
                $inclineIgnored,
            );
        }

        if ('outdoor_bike' === $kind) {
            if (null !== $speedKmh && $speedKmh > 0) {
                $selection = $this->catalog->selectOutdoorCycling(
                    CalorieCatalog::kmhToMph($speedKmh),
                );

                if (null === $selection) {
                    return $this->incomplete(
                        self::STATUS_UNSUPPORTED,
                        'unsupported_bike_speed',
                        'Скорость выше 19 mph не поддерживается в первой версии. Укажите калории вручную.',
                        $inputs,
                        $warnings,
                        $weight,
                        false,
                    );
                }

                $warnings[] = 'Пульс не корректирует MET.';

                return $this->complete(
                    $selection['entry'],
                    'bike_outdoor_speed',
                    $weight,
                    $duration,
                    $inputs,
                    $warnings,
                    $selection['approximate'],
                    false,
                );
            }

            $categories = $this->catalog->outdoorCyclingIntensity();

            if (null !== $intensity && isset($categories[$intensity])) {
                $warnings[] = 'Пульс не корректирует MET.';

                return $this->complete(
                    $categories[$intensity],
                    'bike_outdoor_intensity',
                    $weight,
                    $duration,
                    $inputs,
                    $warnings,
                    false,
                    false,
                );
            }

            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_bike_speed_or_intensity',
                'Укажите скорость или выберите нагрузку для автоматического расчёта.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        if ('stationary_bike' === $kind) {
            if (null !== $power) {
                $selection = $this->catalog->selectStationaryCycling($power);

                if (null === $selection) {
                    return $this->incomplete(
                        self::STATUS_UNSUPPORTED,
                        'unsupported_bike_power',
                        'Мощность вне поддерживаемого диапазона 25–305 Вт — автоматический расчёт недоступен. Укажите калории вручную.',
                        $inputs,
                        $warnings,
                        $weight,
                        false,
                    );
                }

                $warnings[] = 'Пульс не корректирует MET.';

                return $this->complete(
                    $selection['entry'],
                    'bike_stationary_power',
                    $weight,
                    $duration,
                    $inputs,
                    $warnings,
                    $selection['approximate'],
                    false,
                );
            }

            if ('general' === $intensity) {
                $warnings[] = 'Пульс не корректирует MET.';

                return $this->complete(
                    $this->catalog->stationaryGeneral(),
                    'bike_stationary_general',
                    $weight,
                    $duration,
                    $inputs,
                    $warnings,
                    false,
                    false,
                );
            }

            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_bike_power_or_general',
                'Укажите мощность или выберите «Общая тренировка».',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        if ('elliptical' === $kind) {
            $categories = $this->catalog->ellipticalIntensity();

            if (null !== $intensity && isset($categories[$intensity])) {
                $warnings[] = 'Пульс не корректирует MET.';

                return $this->complete(
                    $categories[$intensity],
                    'elliptical_intensity',
                    $weight,
                    $duration,
                    $inputs,
                    $warnings,
                    false,
                    false,
                );
            }

            return $this->incomplete(
                self::STATUS_MISSING,
                'missing_elliptical_intensity',
                'Выберите умеренную или интенсивную нагрузку.',
                $inputs,
                $warnings,
                $weight,
                false,
            );
        }

        return $this->incomplete(
            self::STATUS_UNSUPPORTED,
            'unsupported_cardio_kind',
            'Для этого вида кардио автоматический расчёт не поддерживается. Укажите калории вручную.',
            $inputs,
            $warnings,
            $weight,
            false,
        );
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function complete(
        array $entry,
        string $method,
        array $weight,
        int $duration,
        array $inputs,
        array $warnings,
        bool $approximate,
        bool $inclineIgnored,
    ): CalorieCalculation {
        $met = (float) $entry['met'];
        $weightKg = (float) $weight['weight_kg'];
        $active = $this->formula->active($met, $weightKg, $duration);
        $gross = $this->formula->gross($met, $weightKg, $duration);

        if (
            null === $active
            || null === $gross
            || $active > self::MAX_ACTIVE_KCAL
            || $gross > self::MAX_GROSS_KCAL
        ) {
            return $this->incomplete(
                self::STATUS_UNSUPPORTED,
                'result_out_of_range',
                'Расчётное значение выходит за допустимый предел. Укажите калории вручную.',
                $inputs,
                $warnings,
                $weight,
                $inclineIgnored,
            );
        }

        if ($approximate) {
            $warnings[] = 'Значение попало в пробел между категориями справочника — выбрана ближайшая граница, результат приблизительный.';
        }

        $inputs['category_label'] = $entry['label'];
        $inputs['catalog_url'] = $entry['url'];
        $inputs['catalog_name'] = CalorieCatalog::CATALOG_NAME;
        $inputs['notes'] = $this->notesFor($method);

        return new CalorieCalculation(
            mode: self::MODE_AUTO,
            status: self::STATUS_CALCULATED,
            calories: $active,
            activeKcal: $active,
            grossKcal: $gross,
            method: $method,
            metCode: (string) $entry['code'],
            metValue: $met,
            metLabel: (string) $entry['label'],
            weightKg: $weightKg,
            weightSource: $weight['weight_source'],
            inputs: $inputs,
            warnings: $this->uniqueWarnings($warnings),
            approximate: $approximate,
            inclineIgnored: $inclineIgnored,
            catalogVersion: CalorieCatalog::CATALOG_VERSION,
            formulaVersion: CalorieCatalog::FORMULA_VERSION,
        );
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     * @param array{weight_kg: float|null, weight_source: string|null, warning: string|null} $weight
     */
    private function incomplete(
        string $status,
        string $method,
        string $reason,
        array $inputs,
        array $warnings,
        array $weight,
        bool $inclineIgnored,
    ): CalorieCalculation {
        $inputs['reason'] = $reason;
        $inputs['catalog_name'] = CalorieCatalog::CATALOG_NAME;

        return new CalorieCalculation(
            mode: self::MODE_AUTO,
            status: $status,
            calories: null,
            activeKcal: null,
            grossKcal: null,
            method: $method,
            metCode: null,
            metValue: null,
            metLabel: null,
            weightKg: $weight['weight_kg'],
            weightSource: $weight['weight_source'],
            inputs: $inputs,
            warnings: $this->uniqueWarnings($warnings),
            approximate: false,
            inclineIgnored: $inclineIgnored,
            catalogVersion: CalorieCatalog::CATALOG_VERSION,
            formulaVersion: CalorieCatalog::FORMULA_VERSION,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calculationInputsChanged(Workout $existing, array $data): bool
    {
        if (self::MODE_AUTO !== $existing->calories_mode) {
            return true;
        }

        if (null === $existing->catalog_version || null === $existing->formula_version) {
            return true;
        }

        if ($existing->type->value !== (string) $data['type']) {
            return true;
        }

        if ((int) $existing->duration_seconds !== (int) $data['duration_seconds']) {
            return true;
        }

        if ($existing->date->format('Y-m-d') !== (string) $data['date']) {
            return true;
        }

        if (!$this->sameFloat($existing->distance_km, $data['distance_km'] ?? null)) {
            return true;
        }

        if (!$this->sameFloat($existing->incline_percent, $data['incline_percent'] ?? null)) {
            return true;
        }

        if ((bool) $existing->incline_ignored !== (bool) ($data['incline_ignored'] ?? false)) {
            return true;
        }

        if (($existing->strength_character ?? null) !== ($data['strength_character'] ?? null)) {
            return true;
        }

        if (($existing->activity_kind ?? null) !== ($data['activity_kind'] ?? null)) {
            return true;
        }

        if (($existing->cardio_intensity ?? null) !== ($data['cardio_intensity'] ?? null)) {
            return true;
        }

        $existingPower = null === $existing->power_watts ? null : (int) $existing->power_watts;
        $newPower = isset($data['power_watts']) ? (int) $data['power_watts'] : null;

        return $existingPower !== $newPower;
    }

    private function sameFloat(mixed $left, mixed $right): bool
    {
        if (null === $left && null === $right) {
            return true;
        }

        if (null === $left || null === $right) {
            return false;
        }

        return abs((float) $left - (float) $right) <= self::EPSILON;
    }

    /**
     * @return array<int, string>
     */
    private function notesFor(string $method): array
    {
        $notes = [
            'Расход показан сверх условного покоя; общий расход указан отдельно.',
        ];

        if ('strength_character' === $method) {
            $notes[] = 'MET применяется ко всей длительности с обычными паузами; по тоннажу и повторениям категория не угадывается.';
        }

        if (str_starts_with($method, 'bike_') || 'elliptical_intensity' === $method) {
            $notes[] = 'Пульс не корректирует MET.';
        }

        return $notes;
    }

    /**
     * @param array<int, string> $warnings
     *
     * @return array<int, string>
     */
    private function uniqueWarnings(array $warnings): array
    {
        return array_values(array_unique(array_filter(
            $warnings,
            static fn (mixed $warning): bool => is_string($warning) && '' !== $warning,
        )));
    }
}
