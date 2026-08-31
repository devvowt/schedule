<?php

namespace App\Services\Execution;

use App\Enums\RunStatus;

/**
 * Resultado bruto de uma execução, antes de virar linha em task_runs.
 */
class ExecutionResult
{
    public function __construct(
        public readonly RunStatus $status,
        public readonly ?string $output = null,
        public readonly ?string $error = null,
        public readonly ?int $exitCode = null,
        public readonly ?int $httpStatus = null,
    ) {}

    public static function success(?string $output = null, ?int $exitCode = 0, ?int $httpStatus = null): self
    {
        return new self(RunStatus::Success, $output, null, $exitCode, $httpStatus);
    }

    public static function failed(string $error, ?string $output = null, ?int $exitCode = null, ?int $httpStatus = null): self
    {
        return new self(RunStatus::Failed, $output, $error, $exitCode, $httpStatus);
    }

    public static function timeout(string $error, ?string $output = null): self
    {
        return new self(RunStatus::Timeout, $output, $error);
    }

    public function isSuccess(): bool
    {
        return $this->status === RunStatus::Success;
    }
}
