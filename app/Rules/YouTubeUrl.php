<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail("The $attribute must be a valid URL.");
        }

        if (strpos($value, 'youtube.com') === false) {
            $fail("The $attribute must be a valid YouTube URL.");
        }

        if (strpos($value, 'watch?v=') === false) {
            $fail("The $attribute must be a valid YouTube URL.");
        }
    }
}
