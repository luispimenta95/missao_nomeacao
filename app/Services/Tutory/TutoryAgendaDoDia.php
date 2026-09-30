<?php

namespace App\Services\Tutory;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Horários dos jobs da Tutory em America/Sao_Paulo (UTC−3, o ano inteiro).
 *
 * O job só está devido no minuto marcado e nos 4 minutos seguintes.
 * Um tick às 11:05 não dispara a sincronização das 06:00.
 */
class TutoryAgendaDoDia
{
    public const HEARTBEAT = 'tutory.scheduler.heartbeat';

    public const STALE_SEGUNDOS = 1800;

    public const TOLERANCIA_MINUTOS = 4;

    private const FUSO = 'America/Sao_Paulo';

    /**
     * @return list<array{id: string, comando: string, argumentos: array<string, bool|string>, chave: ?string}>
     */
    public function devidos(DateTimeInterface $agora): array
    {
        $sp = $this->emSaoPaulo($agora);
        $dia = (int) $sp->format('j');
        $data = $sp->format('Y-m-d');
        $jobs = [];

        if (in_array($dia, [1, 16], true) && $this->noHorario($sp, 0, 5)) {
            $jobs[] = $this->job(
                'liberar-periodos',
                'tutory:liberar-periodos-pdf',
                [],
                'tutory.job.liberar-periodos.'.$data,
            );
        }

        if ($this->noHorario($sp, 6, 0)) {
            $jobs[] = $this->job(
                'sincronizar-alunos',
                'tutory:sincronizar-alunos',
                [],
                'tutory.job.sincronizar-alunos.'.$data,
            );
        }

        if ($this->janelaRelatorio($sp, 16)) {
            $jobs[] = $this->job(
                'relatorio-1',
                'tutory:baixar-relatorios',
                ['--periodo' => '1', '--se-pendente' => true],
                null,
            );
        }

        if ($this->janelaRelatorio($sp, 1)) {
            $jobs[] = $this->job(
                'relatorio-2',
                'tutory:baixar-relatorios',
                ['--periodo' => '2', '--se-pendente' => true],
                null,
            );
        }

        return $jobs;
    }

    public function emSaoPaulo(DateTimeInterface $agora): DateTimeImmutable
    {
        $dt = $agora instanceof DateTimeImmutable
            ? $agora
            : DateTimeImmutable::createFromInterface($agora);

        return $dt->setTimezone(new DateTimeZone(self::FUSO));
    }

    /**
     * Dia de abertura (16 = período 1, 1 = período 2): 10:30.
     * Retentativa de hora em hora, no minuto 0, das 11:00 às 22:00,
     * no dia de abertura e no dia seguinte, se o envio não concluiu.
     */
    private function janelaRelatorio(DateTimeImmutable $sp, int $diaAbertura): bool
    {
        $dia = (int) $sp->format('j');
        if ($dia === $diaAbertura && $this->noHorario($sp, 10, 30)) {
            return true;
        }
        if ($dia !== $diaAbertura && $dia !== $diaAbertura + 1) {
            return false;
        }

        $hora = (int) $sp->format('G');
        $minuto = (int) $sp->format('i');

        return $hora >= 11 && $hora <= 22 && $minuto <= self::TOLERANCIA_MINUTOS;
    }

    private function noHorario(DateTimeImmutable $sp, int $hora, int $minuto): bool
    {
        $delta = $this->minutos($sp) - ($hora * 60 + $minuto);

        return $delta >= 0 && $delta <= self::TOLERANCIA_MINUTOS;
    }

    private function minutos(DateTimeImmutable $sp): int
    {
        return ((int) $sp->format('G')) * 60 + (int) $sp->format('i');
    }

    /**
     * @param  array<string, bool|string>  $argumentos
     * @return array{id: string, comando: string, argumentos: array<string, bool|string>, chave: ?string}
     */
    private function job(string $id, string $comando, array $argumentos, ?string $chave): array
    {
        return [
            'id' => $id,
            'comando' => $comando,
            'argumentos' => $argumentos,
            'chave' => $chave,
        ];
    }
}
