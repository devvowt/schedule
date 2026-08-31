<?php

namespace App\Rules;

use App\Services\Execution\CommandTaskExecutor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedShellCommand implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CommandTaskExecutor::isAllowed((string) $value)) {
            $allowed = implode(', ', config('scheduler.commands.allowlist', []));

            $fail("O binário \"".CommandTaskExecutor::binaryOf((string) $value)."\" não está liberado. Permitidos: {$allowed}.");
        }
    }
}
