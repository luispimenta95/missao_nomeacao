<?php

namespace App\Services\Tutory;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Janelas dos jobs da Tutory em America/Sao_Paulo.
 *
 * O tick pode chegar depois do minuto exato (cron atrasado, schedule do
 * GitHub descartado). A partir do horário de abertura o job continua devido
 * até ser concluído naquele dia — ou, no caso do PDF, até o fim da retentativa.
 */
class TutoryAgendaDoDia
{
    public const HEARTBEAT = 'tutory.scheduler.heartbeat';

    public const STALE_SEGUNDOS = 1800;

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

        if (in_array($dia, [1, 16], true) && $this->aPartirDe($sp, 0, 5)) {
            $jobs[] = $this->job(
                'liberar-periodos',
                'tutory:liberar-periodos-pdf',
                [],
                'tutory.job.liberar-periodos.'.$data,
            );
        }

        if ($this->aPartirDe($sp, 6, 0)) {
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
     * Dia de abertura (16 = período 1, 1 = período 2): 10:30–22:59.
     * Dia seguinte: 11:00–22:59, para retentar se o envio não concluiu.
     */
    private function janelaRelatorio(DateTimeImmutable $sp, int $diaAbertura): bool
    {
        $dia = (int) $sp->format('j');
        if ($dia === $diaAbertura) {
            return $this->aPartirDe($sp, 10, 30) && $this->ate($sp, 22, 59);
        }
        if ($dia === $diaAbertura + 1) {
            return $this->aPartirDe($sp, 11, 0) && $this->ate($sp, 22, 59);
        }

        return false;
    }

    private function aPartirDe(DateTimeImmutable $sp, int $hora, int $minuto): bool
    {
        return $this->minutos($sp) >= ($hora * 60 + $minuto);
    }

    private function ate(DateTimeImmutable $sp, int $hora, int $minuto): bool
    {
        return $this->minutos($sp) <= ($hora * 60 + $minuto);
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
