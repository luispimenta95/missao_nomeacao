<?php

namespace App\Services\Alunos;

use App\Models\Aluno;
use App\Models\Configuracao;

class TextoBoasVindasNovato
{
    public const CHAVE = 'email.boas_vindas_novato';

    public static function padrao(): string
    {
        return <<<'TXT'
Olá, {NOME}!

Que bom ter você na mentoria. Seu cadastro ainda tem menos de 15 dias, então este envio não traz o relatório em PDF: o intervalo é curto demais para os números representarem a sua rotina de estudos.

No próximo período, se esses 15 dias já tiverem passado, o relatório consolidado chega normalmente.

Qualquer dúvida, é só responder este e-mail.
Nayara
TXT;
    }

    public static function texto(?string $nome = null): string
    {
        $base = Configuracao::valor(self::CHAVE, self::padrao()) ?? self::padrao();
        $nome = trim((string) $nome);

        return str_replace('{NOME}', $nome !== '' ? $nome : 'aluno', $base);
    }

    public static function paraAluno(Aluno $aluno): string
    {
        return self::texto($aluno->nome);
    }
}
