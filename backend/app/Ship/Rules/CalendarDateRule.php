<?php

namespace App\Ship\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strict calendar date in YYYY-MM-DD form.
 *
 * PHP's DateTime would silently normalize 2025-02-30 to 2025-03-02, which is
 * not acceptable for day boundaries: a normalized date would move a food or
 * water entry to another day. checkdate() rejects impossible dates instead.
 */
final class CalendarDateRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || 1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            $fail('Укажите дату в формате ГГГГ-ММ-ДД.');

            return;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year < 1900 || $year > 9999 || !checkdate($month, $day, $year)) {
            $fail('Укажите действительную календарную дату.');

            return;
        }

        if ($matches[1] . '-' . $matches[2] . '-' . $matches[3] !== $value) {
            $fail('Укажите действительную календарную дату.');
        }
    }
}
