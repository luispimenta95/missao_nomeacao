<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Roda os jobs devidos no tick e impede uma segunda execução no mesmo dia.
 * O comando manual (tutory:sincronizar-alunos etc.) não passa por aqui.
 */
class TutoryAgendaExecutor
{
    public function __construct(private readonly TutoryAgendaDoDia $agenda = new TutoryAgendaDoDia) {}

    /**
     * @param  callable(string, array<string, bool|string>): int  $rodar
     * @return array{executados: list<string>, pulados: list<string>, falhas: list<string>}
     */
    public function executar(DateTimeInterface $agora, callable $rodar, bool $incluirRelatorios = true): array
    {
        $sp = $this->agenda->emSaoPaulo($agora);
        Configuracao::definir(TutoryAgendaDoDia::HEARTBEAT, $sp->format(DateTimeInterface::ATOM));

        $executados = [];
        $pulados = [];
        $falhas = [];

        foreach ($this->agenda->devidos($sp) as $job) {
            if (! $incluirRelatorios && $job['chave'] === null) {
                continue;
            }

            if ($this->relatorioJaEncerrado($job, $sp)) {
                $pulados[] = $job['id'];

                continue;
            }

            $chave = $job['chave'];
            if ($chave !== null && ! $this->reservar($chave)) {
                $pulados[] = $job['id'];

                continue;
            }

            Log::info('[tutory:executar-agendados] iniciando '.$job['id']);

            try {
                $codigo = $rodar($job['comando'], $job['argumentos']);
            } catch (Throwable $exc) {
                $codigo = 1;
                Log::error('[tutory:executar-agendados] '.$job['id'].' exception: '.$exc->getMessage());
            }

            if ($codigo === 0) {
                if ($chave !== null) {
                    $this->concluir($chave);
                }
                $executados[] = $job['id'];

                continue;
            }

            if ($chave !== null) {
                $this->liberar($chave);
            }
            $falhas[] = $job['id'];
            Log::error('[tutory:executar-agendados] '.$job['id'].' retornou código '.$codigo);
        }

        return [
            'executados' => $executados,
            'pulados' => $pulados,
            'falhas' => $falhas,
        ];
    }

    /**
     * @param  array{id: string, comando: string, argumentos: array<string, bool|string>, chave: ?string}  $job
     */
    private function relatorioJaEncerrado(array $job, DateTimeImmutable $sp): bool
    {
        if ($job['chave'] !== null) {
            return false;
        }

        $periodo = $job['argumentos']['--periodo'] ?? null;
        if (! is_string($periodo) || ! in_array($periodo, ['1', '2'], true)) {
            return false;
        }

        return ! (new TutoryRelatorioAgenda)->deveExecutar($periodo, $sp);
    }

    private function reservar(string $chave): bool
    {
        try {
            return DB::transaction(function () use ($chave): bool {
                $row = Configuracao::query()->where('chave', $chave)->lockForUpdate()->first();
                $valor = $row === null ? null : (string) $row->valor;
                if ($valor !== null && $valor !== '' && $this->vigente($valor)) {
                    return false;
                }

                Configuracao::definir(
                    $chave,
                    'running:'.now()->format(DateTimeInterface::ATOM)
                );

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function vigente(string $valor): bool
    {
        if (str_starts_with($valor, 'done:')) {
            return true;
        }
        if (! str_starts_with($valor, 'running:')) {
            return false;
        }

        $inicio = strtotime(substr($valor, strlen('running:')));

        return $inicio !== false && (time() - $inicio) < TutoryAgendaDoDia::STALE_SEGUNDOS;
    }

    private function concluir(string $chave): void
    {
        Configuracao::definir($chave, 'done:'.now()->format(DateTimeInterface::ATOM));
    }

    private function liberar(string $chave): void
    {
        Configuracao::definir($chave, 'failed:'.now()->format(DateTimeInterface::ATOM));
    }
}
