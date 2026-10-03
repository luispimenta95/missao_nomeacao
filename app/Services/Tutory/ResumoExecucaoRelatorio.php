<?php

namespace App\Services\Tutory;

use App\Models\Aluno;
use DateTimeImmutable;

/**
 * Números e nomes de uma execução de tutory:baixar-relatorios,
 * no formato do resumo enviado ao fim do job.
 */
class ResumoExecucaoRelatorio
{
    public const ENVIADO = 'enviado';

    public const PULADO = 'pulado';

    public const SEM_PDF = 'sem_pdf';

    public const INVALIDO = 'invalido';

    public const FALHA = 'falha';

    public const BOAS_VINDAS = 'boas_vindas';

    public ?DateTimeImmutable $fim = null;

    public ?string $logArquivo = null;

    public ?string $erroFatal = null;

    public bool $bancoIndisponivel = false;

    public int $pdfsRemovidos = 0;

    /**
     * @var list<array{nome: string, sucesso: bool}>
     */
    public array $geracoes = [];

    /**
     * @var list<array{nome: string, email: string, situacao: string}>
     */
    public array $envios = [];

    /**
     * Alunos ativos com menos de 15 dias: entram no resumo, sem PDF e sem métrica.
     *
     * @var list<array{nome: string, email: string, situacao: string}>
     */
    public array $novatos = [];

    public ?string $nota = null;

    public function __construct(
        public DateTimeImmutable $inicio,
        public string $periodoRotulo,
        public string $motor,
        public string $chaveEnvio,
        public string $pasta,
        public bool $teste = false,
        public string $fuso = 'America/Sao_Paulo',
    ) {}

    public function duracao(): string
    {
        $fim = $this->fim ?? $this->inicio;
        $total = max(0, $fim->getTimestamp() - $this->inicio->getTimestamp());
        $minutos = intdiv($total, 60);
        $segundos = $total % 60;
        if ($minutos > 0) {
            return $minutos.'min '.$segundos.'s';
        }

        return $segundos.'s';
    }

    public function ativos(): int
    {
        $nomes = [];
        foreach ($this->geracoes as $geracao) {
            $nomes[Aluno::normalizarNome($geracao['nome'])] = true;
        }
        foreach ($this->novatos as $novato) {
            $nomes[Aluno::normalizarNome($novato['nome'])] = true;
        }

        return count($nomes);
    }

    public function pdfsGerados(): int
    {
        return count(array_filter($this->geracoes, static fn (array $g): bool => $g['sucesso']));
    }

    public function falhasGeracao(): int
    {
        return count(array_filter($this->geracoes, static fn (array $g): bool => ! $g['sucesso']));
    }

    public function emailsBoasVindas(): int
    {
        return count(array_filter(
            $this->novatos,
            static fn (array $novato): bool => $novato['situacao'] === self::BOAS_VINDAS
        ));
    }

    public function registrarNovato(string $nome, string $email = '', string $situacao = ''): void
    {
        $chave = Aluno::normalizarNome($nome);
        foreach ($this->novatos as $indice => $novato) {
            if (Aluno::normalizarNome($novato['nome']) !== $chave) {
                continue;
            }
            if ($email !== '') {
                $this->novatos[$indice]['email'] = $email;
            }
            if ($situacao !== '') {
                $this->novatos[$indice]['situacao'] = $situacao;
            }

            return;
        }

        $this->novatos[] = [
            'nome' => $nome,
            'email' => $email,
            'situacao' => $situacao,
        ];
    }

    public function emailsEnviados(): int
    {
        return $this->contarEnvio(self::ENVIADO);
    }

    public function emailsPulados(): int
    {
        return $this->contarEnvio(self::PULADO);
    }

    public function falhasEnvio(): int
    {
        return $this->contarEnvio(self::SEM_PDF)
            + $this->contarEnvio(self::INVALIDO)
            + $this->contarEnvio(self::FALHA);
    }

    /**
     * @return list<array{indicador: string, resultado: int}>
     */
    public function indicadores(): array
    {
        $linhas = [
            ['indicador' => 'Alunos ativos encontrados', 'resultado' => $this->ativos()],
        ];
        if ($this->novatos !== []) {
            $linhas[] = [
                'indicador' => 'Alunos novatos (sem PDF e sem métricas)',
                'resultado' => count($this->novatos),
            ];
        }
        $linhas[] = ['indicador' => 'PDFs consolidados gerados', 'resultado' => $this->pdfsGerados()];
        $linhas[] = ['indicador' => 'Falhas na geração dos PDFs', 'resultado' => $this->falhasGeracao()];
        $linhas[] = ['indicador' => 'E-mails enviados', 'resultado' => $this->emailsEnviados()];
        if ($this->novatos !== []) {
            $linhas[] = ['indicador' => 'E-mails de boas-vindas', 'resultado' => $this->emailsBoasVindas()];
        }
        $linhas[] = ['indicador' => 'E-mails pulados (recebe_email=false)', 'resultado' => $this->emailsPulados()];
        $linhas[] = ['indicador' => 'Falhas / PDF não localizado no envio', 'resultado' => $this->falhasEnvio()];
        $linhas[] = ['indicador' => 'PDFs removidos após o envio', 'resultado' => $this->pdfsRemovidos];

        return $linhas;
    }

    /**
     * PDFs gerados e, em seguida, alunos novatos que ficaram fora da geração.
     *
     * @return list<array{nome: string, pdf: string, email: string}>
     */
    public function linhasDaExecucao(): array
    {
        $linhas = $this->linhasComPdf();
        $novatos = $this->novatos;
        usort($novatos, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        foreach ($novatos as $novato) {
            $linhas[] = [
                'nome' => $novato['nome'],
                'pdf' => 'Não gerado — aluno novato',
                'email' => $novato['situacao'] === ''
                    ? 'Sem envio registrado no log'
                    : $this->rotuloEmail($novato['situacao']),
            ];
        }

        return $linhas;
    }

    /**
     * @return list<array{nome: string, pdf: string, email: string}>
     */
    public function linhasComPdf(): array
    {
        $envios = [];
        foreach ($this->envios as $envio) {
            $envios[Aluno::normalizarNome($envio['nome'])] = $envio;
        }

        $linhas = [];
        foreach ($this->geracoes as $geracao) {
            if (! $geracao['sucesso']) {
                continue;
            }
            $envio = $envios[Aluno::normalizarNome($geracao['nome'])] ?? null;
            $linhas[] = [
                'nome' => $geracao['nome'],
                'pdf' => 'Gerado',
                'email' => $envio === null
                    ? 'Sem envio registrado no log'
                    : $this->rotuloEmail($envio['situacao']),
                'ordem' => $envio === null ? 4 : $this->ordemEmail($envio['situacao']),
            ];
        }

        usort($linhas, static function (array $a, array $b): int {
            return [$a['ordem'], mb_strtolower($a['nome'])] <=> [$b['ordem'], mb_strtolower($b['nome'])];
        });

        return array_map(static fn (array $linha): array => [
            'nome' => $linha['nome'],
            'pdf' => $linha['pdf'],
            'email' => $linha['email'],
        ], $linhas);
    }

    /**
     * @return list<array{nome: string, email: string, situacao: string}>
     */
    public function semPdf(): array
    {
        return array_values(array_filter(
            $this->envios,
            static fn (array $envio): bool => $envio['situacao'] === self::SEM_PDF
        ));
    }

    /**
     * @return list<string>
     */
    public function geradosSemEnvio(): array
    {
        $nomesEnvio = array_map(
            static fn (array $envio): string => Aluno::normalizarNome($envio['nome']),
            $this->envios
        );
        $faltam = [];
        foreach ($this->geracoes as $geracao) {
            if (! $geracao['sucesso']) {
                continue;
            }
            if (! in_array(Aluno::normalizarNome($geracao['nome']), $nomesEnvio, true)) {
                $faltam[] = $geracao['nome'];
            }
        }

        return $faltam;
    }

    /**
     * @return list<string>
     */
    public function divergencias(): array
    {
        $paragrafos = [];
        $semPdf = $this->semPdf();
        if ($semPdf !== []) {
            $nomes = array_map(
                static fn (array $envio): string => $envio['nome'].' ('.$envio['email'].')',
                $semPdf
            );
            $quantidade = count($semPdf);
            $verbo = $quantidade === 1 ? 'apareceu' : 'apareceram';
            $substantivo = $quantidade === 1 ? 'nome' : 'nomes';
            $pronome = $quantidade === 1
                ? 'Esse nome não faz'
                : 'Esses '.$this->porExtenso($quantidade).' não fazem';
            $paragrafos[] = $this->porExtenso($quantidade, true).' '.$substantivo.' '.$verbo
                .' na etapa de envio, mas o sistema informou que não encontrou PDF correspondente: '
                .$this->juntar($nomes).'. '
                .$pronome.' parte dos '.$this->pdfsGerados()
                .' alunos para os quais os PDFs foram gerados nesta execução.';
        }

        $semRegistro = $this->geradosSemEnvio();
        if ($semRegistro !== []) {
            $verbo = count($semRegistro) === 1 ? 'teve' : 'tiveram';
            $paragrafos[] = 'Ponto de atenção: '.$this->juntar($semRegistro)
                .' '.$verbo.' PDF gerado, porém não aparece com status de envio na etapa de e-mail. '
                .'Isso indica uma possível diferença entre a lista usada para gerar os PDFs e a lista usada para enviar os e-mails.';
        }

        $invalidos = array_values(array_filter(
            $this->envios,
            static fn (array $envio): bool => $envio['situacao'] === self::INVALIDO
        ));
        if ($invalidos !== []) {
            $nomes = array_map(static fn (array $envio): string => $envio['nome'], $invalidos);
            $paragrafos[] = 'E-mail inválido no cadastro: '.$this->juntar($nomes).'.';
        }

        $falhas = array_values(array_filter(
            $this->envios,
            static fn (array $envio): bool => $envio['situacao'] === self::FALHA
        ));
        if ($falhas !== []) {
            $nomes = array_map(static fn (array $envio): string => $envio['nome'], $falhas);
            $paragrafos[] = 'Falha ao disparar o e-mail: '.$this->juntar($nomes).'.';
        }

        return $paragrafos;
    }

    /**
     * @return list<string>
     */
    public function conclusao(): array
    {
        $paragrafos = [];
        if ($this->erroFatal !== null && $this->erroFatal !== '') {
            $paragrafos[] = 'A execução terminou com erro: '.$this->erroFatal;
        }
        if ($this->bancoIndisponivel) {
            $paragrafos[] = 'O banco local estava indisponível. Os e-mails não foram enviados e os PDFs foram preservados.';
        }

        $gerados = $this->pdfsGerados();
        $previstos = count($this->geracoes);
        $total = $this->ativos();
        if ($total === 0) {
            $texto = 'Nenhum aluno ativo entrou nesta execução.';
        } elseif ($previstos === 0) {
            $texto = 'Nenhum PDF entrou na geração: os '.$total.' alunos ativos são novatos.';
        } elseif ($this->falhasGeracao() === 0) {
            $texto = 'A geração dos relatórios funcionou integralmente: '.$gerados.' de '.$previstos.' PDFs foram gerados, sem falhas.';
        } else {
            $texto = 'A geração concluiu com '.$gerados.' de '.$previstos.' PDFs. Falhas na geração: '.$this->falhasGeracao().'.';
        }

        if ($this->semPdf() !== [] || $this->contarEnvio(self::FALHA) > 0) {
            $texto .= ' O problema está concentrado na etapa de associação/envio dos e-mails. A principal hipótese indicada pelo próprio log é divergência entre os nomes do cadastro administrativo e os nomes retornados pelo Tutory.';
        } elseif ($total > 0 && $this->geradosSemEnvio() === [] && $this->falhasEnvio() === 0) {
            $texto .= ' O envio acompanhou os PDFs gerados, sem falha de localização.';
        }

        $semRegistro = $this->geradosSemEnvio();
        if ($semRegistro !== []) {
            $verbo = count($semRegistro) === 1 ? 'recebeu' : 'receberam';
            $texto .= ' Também deve ser verificado por que '
                .$this->juntar($semRegistro)
                .' não '.$verbo.' um status de envio no log.';
        }

        $paragrafos[] = $texto;

        if ($this->novatos !== []) {
            $quantidade = count($this->novatos);
            $nomes = array_map(static fn (array $novato): string => $novato['nome'], $this->novatos);
            $substantivo = $quantidade === 1 ? 'aluno novato' : 'alunos novatos';
            $verbo = $quantidade === 1 ? 'ficou' : 'ficaram';
            $paragrafos[] = $this->porExtenso($quantidade, true).' '.$substantivo
                .' (cadastro com menos de 15 dias) '.$verbo
                .' fora da geração e da análise de métricas: '.$this->juntar($nomes).'. '
                .'Boas-vindas enviadas: '.$this->emailsBoasVindas().'.';
        }

        return $paragrafos;
    }

    public function rotuloEmail(string $situacao): string
    {
        return match ($situacao) {
            self::ENVIADO => 'Enviado',
            self::BOAS_VINDAS => 'E-mail de boas-vindas',
            self::PULADO => 'Não enviado — recebe_email=false',
            self::INVALIDO => 'Não enviado — e-mail inválido',
            self::FALHA => 'Falha ao enviar',
            default => 'Sem envio registrado no log',
        };
    }

    private function ordemEmail(string $situacao): int
    {
        return match ($situacao) {
            self::PULADO => 0,
            self::INVALIDO => 1,
            self::FALHA => 2,
            self::ENVIADO => 3,
            default => 4,
        };
    }

    private function contarEnvio(string $situacao): int
    {
        return count(array_filter(
            $this->envios,
            static fn (array $envio): bool => $envio['situacao'] === $situacao
        ));
    }

    private function porExtenso(int $quantidade, bool $maiuscula = false): string
    {
        $palavras = [
            1 => 'um',
            2 => 'dois',
            3 => 'três',
            4 => 'quatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'sete',
            8 => 'oito',
            9 => 'nove',
            10 => 'dez',
        ];
        $texto = $palavras[$quantidade] ?? (string) $quantidade;
        if ($maiuscula && isset($palavras[$quantidade])) {
            $texto = mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
        }

        return $texto;
    }

    /**
     * @param  list<string>  $itens
     */
    private function juntar(array $itens): string
    {
        $itens = array_values($itens);
        $total = count($itens);
        if ($total === 0) {
            return '';
        }
        if ($total === 1) {
            return $itens[0];
        }
        $ultimo = array_pop($itens);

        return implode(', ', $itens).' e '.$ultimo;
    }
}
