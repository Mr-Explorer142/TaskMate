<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HttpUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid http or https URL.');

            return;
        }

        $url = trim($value);

        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url)) {
            $fail('The :attribute must be a valid http or https URL.');

            return;
        }

        $parts = parse_url($url);

        if ($parts === false
            || empty($parts['scheme'])
            || empty($parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail('The :attribute must be a valid http or https URL.');
        }
    }
}
