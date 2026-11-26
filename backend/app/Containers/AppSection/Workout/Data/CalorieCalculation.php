<?php

namespace App\Containers\AppSection\Workout\Data;

final class CalorieCalculation
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<int, string> $warnings
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $status,
        public readonly int|null $calories,
        public readonly int|null $activeKcal,
        public readonly int|null $grossKcal,
        public readonly string|null $method,
        public readonly string|null $metCode,
        public readonly float|null $metValue,
        public readonly string|null $metLabel,
        public readonly float|null $weightKg,
        public readonly string|null $weightSource,
        public readonly array $inputs,
        public readonly array $warnings,
        public readonly bool $approximate,
        public readonly bool $inclineIgnored,
        public readonly string|null $catalogVersion = null,
        public readonly string|null $formulaVersion = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toColumns(): array
    {
        return [
            'calories' => $this->calories,
            'active_kcal' => $this->activeKcal,
            'gross_kcal' => $this->grossKcal,
            'calories_mode' => $this->mode,
            'calories_status' => $this->status,
            'calorie_method' => $this->method,
            'met_code' => $this->metCode,
            'met_value' => $this->metValue,
            'met_label' => $this->metLabel,
            'catalog_version' => $this->catalogVersion,
            'formula_version' => $this->formulaVersion,
            'weight_kg' => $this->weightKg,
            'weight_kg_exact' => $this->weightKg,
            'weight_source' => $this->weightSource,
            'calorie_inputs' => $this->inputs,
            'calorie_warnings' => $this->warnings,
            'calorie_approximate' => $this->approximate,
            'incline_ignored' => $this->inclineIgnored,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'calories_mode' => $this->mode,
            'calories_status' => $this->status,
            'calories' => $this->calories,
            'active_kcal' => $this->activeKcal,
            'gross_kcal' => $this->grossKcal,
            'calorie_method' => $this->method,
            'met_code' => $this->metCode,
            'met_value' => $this->metValue,
            'met_label' => $this->metLabel,
            'weight_kg' => $this->weightKg,
            'weight_source' => $this->weightSource,
            'calorie_inputs' => $this->inputs,
            'calorie_warnings' => $this->warnings,
            'calorie_approximate' => $this->approximate,
            'incline_ignored' => $this->inclineIgnored,
            'catalog_version' => $this->catalogVersion,
            'formula_version' => $this->formulaVersion,
        ];
    }
}
