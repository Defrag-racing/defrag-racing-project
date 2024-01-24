<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use Illuminate\Support\Str;

class MddProfile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::startsWith($value, 'https://q3df.org/profil?id=')) {
            return;
        }

        if (Str::startsWith($value, 'http://q3df.org/profil?id=')) {
            return;
        }

        $fail('The profile link must be in the format: https://q3df.org/profil?id=YOUR_ID');
    }
}
