<?php

namespace App\Ship\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects INF/NAN and non-scalar values before numeric validation.
 *
 * JSON payloads can decode a huge exponent to INF, and INF passes PHP's
 * is_numeric()/Laravel's `numeric` rule. Persisting or aggregating such a value
 * would corrupt totals, so every fractional field also runs through this rule.
 */
final class FiniteNumberRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            $fail('Укажите числовое значение.');

            return;
        }

        if (!is_finite((float) $value)) {
            $fail('Укажите конечное числовое значение.');
        }
    }
}
