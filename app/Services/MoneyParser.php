<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class MoneyParser
{
    public function parse(string $value): int
    {
        $value = trim($value);
        if (strlen($value) > 12 || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive KES amount with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($minor < 1 || $minor > config('banking.limit_minor')) {
            throw ValidationException::withMessages(['amount' => 'Amount must be between KES 0.01 and the demo operation limit.']);
        }

        return $minor;
    }
}
