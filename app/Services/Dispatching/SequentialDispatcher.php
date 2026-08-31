<?php

namespace App\Services\Dispatching;

use App\Jobs\ExecuteTaskRun;
use App\Models\TaskRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

/**
 * Modo sequencial.
 *
 * Cinco tarefas marcadas para as 09:00 no mesmo grupo não sobem juntas: elas
 * entram na fila do grupo, na ordem de sequence_order, e a próxima só começa
 * quando a anterior termina.
 *
 * Cada tarefa pode declarar uma defasagem (stagger_minutes) em relação à
 * anterior do grupo:
 *
 *   stagger = 0  →  encadeamento puro (Bus::chain): começa assim que a
 *                   anterior terminar, seja lá quanto ela demore.
 *   stagger = N  →  o despacho é atrasado em N minutos sobre a tarefa
 *                   anterior, acumulando ao longo do grupo.
 *
 * Os dois formatos convivem no mesmo grupo: a lista é quebrada em blocos a
 * cada tarefa com defasagem, e cada bloco vira uma corrente encadeada com seu
 * próprio atraso inicial.
 */
class SequentialDispatcher
{
    /**
     * @param  Collection<int, TaskRun>  $runs  execuções já criadas, ordenadas
     * @return int  número de correntes despachadas
     */
    public function dispatchGroup(string $group, Collection $runs): int
    {
        if ($runs->isEmpty()) {
            return 0;
        }

        $queue = (string) config('scheduler.sequential.queue');
        $chains = $this->buildChains($runs);

        foreach ($chains as $chain) {
            $jobs = array_map(
                fn (TaskRun $run) => (new ExecuteTaskRun($run->id))->onQueue($queue),
                $chain['runs']
            );

            $pending = Bus::chain($jobs)->onQueue($queue);

            if ($chain['delay_minutes'] > 0) {
                $pending->delay(Carbon::now()->addMinutes($chain['delay_minutes']));
            }

            $pending->dispatch();
        }

        return count($chains);
    }

    /**
     * Quebra o grupo em blocos encadeados, calculando o atraso acumulado.
     *
     * @param  Collection<int, TaskRun>  $runs
     * @return array<int, array{delay_minutes: int, runs: array<int, TaskRun>}>
     */
    protected function buildChains(Collection $runs): array
    {
        $chains = [];
        $current = null;
        $offset = 0;

        foreach ($runs->values() as $index => $run) {
            $stagger = (int) ($run->task?->stagger_minutes ?? 0);

            // A primeira tarefa do grupo nunca é atrasada: a defasagem descreve
            // a distância até a tarefa anterior, e não existe anterior aqui.
            $startsNewChain = $current === null || ($index > 0 && $stagger > 0);

            if ($startsNewChain) {
                if ($current !== null) {
                    $chains[] = $current;
                    $offset += $stagger;
                }

                $current = ['delay_minutes' => $offset, 'runs' => []];
            }

            $current['runs'][] = $run;

            if ($run->scheduled_for !== null && $offset > 0) {
                $run->forceFill(['scheduled_for' => Carbon::now()->addMinutes($offset)])->save();
            }
        }

        if ($current !== null) {
            $chains[] = $current;
        }

        return $chains;
    }
}
