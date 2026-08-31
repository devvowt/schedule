<?php

namespace App\Services\Dispatching;

/**
 * Resumo de um ciclo do agendador, usado pelo comando e pelos testes.
 */
class DispatchReport
{
    public function __construct(
        public int $due = 0,
        public int $parallel = 0,
        public int $sequential = 0,
        public int $chains = 0,
        public int $skipped = 0,
        public ?string $parallelDriver = null,
        /** @var array<int, string> */
        public array $groups = [],
    ) {}

    public function total(): int
    {
        return $this->parallel + $this->sequential;
    }

    public function toArray(): array
    {
        return [
            'due' => $this->due,
            'parallel' => $this->parallel,
            'sequential' => $this->sequential,
            'chains' => $this->chains,
            'skipped' => $this->skipped,
            'parallel_driver' => $this->parallelDriver,
            'groups' => $this->groups,
        ];
    }
}
