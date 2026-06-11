<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid string.');

            return;
        }

        // min 8 rule
        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters.');
        }

        // max 64 rule
        if (strlen($value) > 64) {
            $fail('The :attribute may not be greater than 64 characters.');
        }

        // password contains at least one uppercase letter
        if (! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        // password contains at least one lowercase letter
        if (! preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
        }

        // password contains at least one number
        if (! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        // password contains at least one special character
        if (! preg_match('/[!@#$%^&*]/', $value)) {
            $fail('The :attribute must contain at least one special character (!@#$%^&*).');
        }

        // password contains these characters: !@#$%^&*
        if (preg_match('/[^A-Za-z0-9!@#$%^&*]/', $value)) {
            $fail('The :attribute contains invalid characters. Only letters, numbers, and !@#$%^&* are allowed.');
        }
    }
}
