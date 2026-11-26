<?php

namespace App\Ship\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject booleans and fractional JSON values for integer-only fields.
 *
 * Laravel's built-in `integer` rule delegates to filter_var(FILTER_VALIDATE_INT),
 * which accepts JSON `true` as 1 and `1.0` as 1. That would let a boolean or a
 * fraction silently create a step count or water increment. This rule keeps the
 * existing contract: a JSON integer or an integer numeric string is accepted,
 * while booleans, nulls, arrays and all floats are rejected.
 */
final class StrictIntegerRule implements ValidationRule
{
    public function __construct(
        private readonly string $message = 'Значение должно быть целым числом.',
    ) {
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (is_int($value)) {
            return;
        }

        if (is_string($value) && 1 === preg_match('/^[+-]?\d+$/', $value)) {
            return;
        }

        $fail($this->message);
    }
}
