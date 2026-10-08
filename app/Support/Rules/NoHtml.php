<?php

declare(strict_types=1);

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoHtml implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        // Rejects `<` followed by a letter, `/`, or `!`
        if (preg_match('/<[a-zA-Z\/!]/', $value)) {
            $fail('The :attribute must not contain HTML tags.');
        }
    }
}
