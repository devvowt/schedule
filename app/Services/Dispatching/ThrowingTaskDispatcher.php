<?php

namespace App\Services\Dispatching;

use Laravel\Octane\Contracts\DispatchesTasks;

/**
 * Fallback plugado no dispatcher do Octane. O padrão do pacote executaria as
 * tarefas em sequência no próprio processo — o que anularia o paralelismo e
 * travaria o agendador. Aqui a indisponibilidade vira exceção, e quem chamou
 * decide o que fazer (no nosso caso: cair para a fila).
 */
class ThrowingTaskDispatcher implements DispatchesTasks
{
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        throw new OctaneUnavailableException('Servidor Octane indisponível para execução paralela.');
    }

    public function dispatch(array $tasks): void
    {
        throw new OctaneUnavailableException('Servidor Octane indisponível para execução paralela.');
    }
}
