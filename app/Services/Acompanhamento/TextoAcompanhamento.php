<?php

namespace App\Services\Acompanhamento;

use DateTimeInterface;

final class TextoAcompanhamento
{
    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, static fn (string $parte): bool => $parte !== ''));
        if ($partes === []) {
            return '?';
        }

        $primeira = mb_substr($partes[0], 0, 1);
        $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

        return mb_strtoupper($primeira.$ultima);
    }

    public static function primeiroNome(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $primeiro = $partes[0] ?? '';

        return $primeiro !== '' ? $primeiro : 'Olá';
    }

    public static function dataPorExtenso(DateTimeInterface $data): string
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $dia = $dias[(int) $data->format('w')] ?? '';
        $mes = $meses[(int) $data->format('n')] ?? '';

        return ucfirst($dia).', '.$data->format('j').' de '.$mes.' de '.$data->format('Y');
    }

    public static function ultimoContato(?DateTimeInterface $quando, DateTimeInterface $hoje): string
    {
        if ($quando === null) {
            return 'Nenhum contato';
        }

        $dias = ContextoAcompanhamento::diasDesde($quando, $hoje);
        $curta = self::dataCurta($quando);
        if ($dias === 0) {
            return 'hoje ('.$curta.')';
        }
        if ($dias === 1) {
            return 'ontem ('.$curta.')';
        }

        return 'há '.$dias.' dias ('.$curta.')';
    }

    public static function proximoContato(?DateTimeInterface $quando, DateTimeInterface $hoje): string
    {
        if ($quando === null) {
            return '—';
        }

        $dias = ContextoAcompanhamento::diasDesde($hoje, $quando);
        $curta = self::dataCurta($quando);
        if ($quando->format('Y-m-d') === $hoje->format('Y-m-d')) {
            return 'Hoje ('.$curta.')';
        }
        if ($dias === 1 && $quando->format('Y-m-d') > $hoje->format('Y-m-d')) {
            return 'Amanhã ('.$curta.')';
        }

        return $quando->format('d/m/Y');
    }

    public static function dataCurta(DateTimeInterface $quando): string
    {
        return $quando->format('d/m');
    }

    public static function dataHora(DateTimeInterface $quando): string
    {
        return $quando->format('d/m/Y').' · '.$quando->format('H:i');
    }
}
