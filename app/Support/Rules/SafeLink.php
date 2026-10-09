<?php

declare(strict_types=1);

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SafeLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (strlen($value) > 2048) {
            $fail('The :attribute is too long.');

            return;
        }

        if (preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            $fail('The :attribute contains invalid characters.');

            return;
        }

        if (str_contains($value, '\\')) {
            $fail('The :attribute contains invalid characters.');

            return;
        }

        if (str_starts_with($value, '//')) {
            $fail('The :attribute must be a valid path or URL.');

            return;
        }

        if (str_starts_with($value, '/')) {
            return;
        }

        if (stripos($value, 'https://') !== 0) {
            $fail('The :attribute must start with / or https://.');

            return;
        }

        $parsed = parse_url($value);
        if ($parsed === false || empty($parsed['host'])) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            $fail('The :attribute must not contain credentials.');

            return;
        }
    }
}
