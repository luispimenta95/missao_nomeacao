<?php

namespace App\Services\Tutory;

use App\Models\Aluno;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Espelha alunos ativos da Tutory na tabela local.
 *
 * Nome da Tutory prevalece. E-mail da Tutory prevalece se o aluno já existir.
 * recebe_email fica sempre true. ativo segue o status da Tutory
 * (lista ativos = true, lista inativos = false). Sem status, o padrão é true.
 * Nome é único: duplicidade só é logada.
 * O cadastro "Aluno teste" da Tutory é ignorado e não entra na tabela local.
 */
class SincronizarAlunosTutory
{
    private const NOME_ALUNO_IGNORADO = 'Aluno teste';

    /** @var callable(string): void */
    private $logger;

    public function __construct(
        private ?TutoryAlunosClient $tutory = null,
        ?callable $logger = null,
    ) {
        $this->logger = $logger ?? static function (string $message): void {
            echo $message.PHP_EOL;
        };
        $this->tutory ??= new TutoryAlunosClient($this->logger);
    }

    /**
     * @return array{criados: int, atualizados: int, inalterados: int, pulados: int, total: int}
     */
    public function run(): array
    {
        try {
            $this->tutory->login();
            $lista = $this->tutory->coletarAlunos();

            return $this->sincronizarLista($lista);
        } finally {
            $this->tutory->encerrar();
        }
    }

    /**
     * @param  list<array{id?: string, nome?: string, email?: string, ativo?: bool}>  $alunosTutory
     * @return array{criados: int, atualizados: int, inalterados: int, pulados: int, total: int}
     */
    public function sincronizarLista(array $alunosTutory): array
    {
        $criados = 0;
        $atualizados = 0;
        $inalterados = 0;
        $pulados = 0;

        foreach ($alunosTutory as $origem) {
            $resultado = $this->sincronizarUm([
                'id' => trim((string) ($origem['id'] ?? '')),
                'nome' => trim((string) ($origem['nome'] ?? '')),
                'email' => mb_strtolower(trim((string) ($origem['email'] ?? ''))),
                'ativo' => array_key_exists('ativo', $origem) ? (bool) $origem['ativo'] : true,
            ]);
            match ($resultado) {
                'criado' => $criados++,
                'atualizado' => $atualizados++,
                'inalterado' => $inalterados++,
                default => $pulados++,
            };
        }

        $resumo = "Sincronização concluída: {$criados} criado(s), {$atualizados} atualizado(s), {$inalterados} inalterado(s), {$pulados} pulado(s).";
        $this->log($resumo);

        return [
            'criados' => $criados,
            'atualizados' => $atualizados,
            'inalterados' => $inalterados,
            'pulados' => $pulados,
            'total' => count($alunosTutory),
        ];
    }

    /**
     * @param  array{id: string, nome: string, email: string, ativo: bool}  $origem
     */
    private function sincronizarUm(array $origem): string
    {
        $nomeTutory = $origem['nome'];
        $emailTutory = $origem['email'];
        $tutoryId = $origem['id'];

        if ($nomeTutory === '') {
            $this->log('Aluno da Tutory ignorado: nome vazio (id='.($tutoryId !== '' ? $tutoryId : '—').').');

            return 'pulado';
        }
        if ($this->eAlunoTeste($nomeTutory)) {
            $this->log('Aluno da Tutory ignorado: "'.$nomeTutory.'" não é cadastrado (id='.($tutoryId !== '' ? $tutoryId : '—').').');

            return 'pulado';
        }
        if ($emailTutory === '' || ! filter_var($emailTutory, FILTER_VALIDATE_EMAIL)) {
            $this->log("Aluno da Tutory ignorado: e-mail ausente ou inválido (nome={$nomeTutory}, id=".($tutoryId !== '' ? $tutoryId : '—').').');

            return 'pulado';
        }

        $aluno = $this->localizar($origem);
        if ($aluno === null) {
            return $this->cadastrar($origem);
        }

        return $this->editar($aluno, $origem);
    }

    /**
     * @param  array{id: string, nome: string, email: string, ativo: bool}  $origem
     */
    private function localizar(array $origem): ?Aluno
    {
        $porId = $origem['id'] !== '' ? Aluno::encontrarPorTutoryId($origem['id']) : null;
        if ($porId !== null) {
            return $porId;
        }
        $porEmail = Aluno::encontrarPorEmail($origem['email']);
        if ($porEmail !== null) {
            return $porEmail;
        }

        $porNome = Aluno::encontrarPorNome($origem['nome']);
        if ($porNome === null) {
            return null;
        }
        $tutoryIdExistente = trim((string) $porNome->tutory_id);
        if ($tutoryIdExistente === '' || $tutoryIdExistente === $origem['id']) {
            return $porNome;
        }

        return null;
    }

    /**
     * @param  array{id: string, nome: string, email: string, ativo: bool}  $origem
     */
    private function cadastrar(array $origem): string
    {
        $donoNome = $this->outroComNome($origem['nome'], null);
        if ($donoNome !== null) {
            $this->logDuplicidade(
                'cadastrar',
                $origem['nome'],
                $origem['id'],
                $donoNome
            );

            return 'pulado';
        }
        $donoEmail = $this->outroComEmail($origem['email'], null);
        if ($donoEmail !== null) {
            $this->logDuplicidade(
                'cadastrar',
                $origem['nome'],
                $origem['id'],
                $donoEmail,
                'e-mail'
            );

            return 'pulado';
        }

        try {
            Aluno::create([
                'tutory_id' => $origem['id'] !== '' ? $origem['id'] : null,
                'nome' => $origem['nome'],
                'email' => $origem['email'],
                'recebe_email' => true,
                'ativo' => $origem['ativo'],
            ]);
        } catch (Throwable $exc) {
            $this->log("Falha ao cadastrar {$origem['nome']}: ".$exc->getMessage());

            return 'pulado';
        }

        $this->log("Cadastrado: {$origem['nome']} <{$origem['email']}> (recebe_email=true)");

        return 'criado';
    }

    /**
     * @param  array{id: string, nome: string, email: string, ativo: bool}  $origem
     */
    private function editar(Aluno $aluno, array $origem): string
    {
        $mudou = false;

        if ($origem['id'] !== '' && (string) $aluno->tutory_id !== $origem['id']) {
            $aluno->tutory_id = $origem['id'];
            $mudou = true;
        }

        if (Aluno::normalizarNome($aluno->nome) !== Aluno::normalizarNome($origem['nome'])) {
            $donoNome = $this->outroComNome($origem['nome'], $aluno->id);
            if ($donoNome !== null) {
                $this->logDuplicidade('editar', $origem['nome'], $origem['id'], $donoNome);
            } else {
                $this->log("Nome divergente: plataforma \"{$aluno->nome}\" → Tutory \"{$origem['nome']}\" (aluno id {$aluno->id}).");
                $aluno->nome = $origem['nome'];
                $mudou = true;
            }
        }

        if (mb_strtolower($aluno->email) !== mb_strtolower($origem['email'])) {
            $donoEmail = $this->outroComEmail($origem['email'], $aluno->id);
            if ($donoEmail !== null) {
                $this->logDuplicidade('editar', $origem['nome'], $origem['id'], $donoEmail, 'e-mail');
            } else {
                $this->log("E-mail divergente: plataforma {$aluno->email} → Tutory {$origem['email']} (aluno id {$aluno->id}).");
                $aluno->email = $origem['email'];
                $mudou = true;
            }
        }

        if (! $aluno->recebe_email) {
            $aluno->recebe_email = true;
            $mudou = true;
        }

        if ((bool) $aluno->ativo !== $origem['ativo']) {
            $aluno->ativo = $origem['ativo'];
            $mudou = true;
        }

        if (! $mudou) {
            return 'inalterado';
        }

        try {
            $aluno->save();
        } catch (Throwable $exc) {
            $this->log("Falha ao editar {$origem['nome']}: ".$exc->getMessage());

            return 'pulado';
        }

        $this->log("Atualizado: {$aluno->nome} <{$aluno->email}> (recebe_email=true)");

        return 'atualizado';
    }

    private function eAlunoTeste(string $nome): bool
    {
        return Aluno::normalizarNome($nome) === Aluno::normalizarNome(self::NOME_ALUNO_IGNORADO);
    }

    private function outroComNome(string $nome, int|string|null $excetoId): ?Aluno
    {
        $encontrado = Aluno::encontrarPorNome($nome);
        if ($encontrado === null) {
            return null;
        }
        if ($excetoId !== null && (int) $encontrado->id === (int) $excetoId) {
            return null;
        }

        return $encontrado;
    }

    private function outroComEmail(string $email, int|string|null $excetoId): ?Aluno
    {
        $encontrado = Aluno::encontrarPorEmail($email);
        if ($encontrado === null) {
            return null;
        }
        if ($excetoId !== null && (int) $encontrado->id === (int) $excetoId) {
            return null;
        }

        return $encontrado;
    }

    private function logDuplicidade(string $acao, string $nome, string $tutoryId, Aluno $existente, string $campo = 'nome'): void
    {
        $idTutory = $tutoryId !== '' ? $tutoryId : '—';
        $mensagem = "Nome duplicado ao {$acao} \"{$nome}\" (Tutory id {$idTutory}). "
            ."Já existe aluno id {$existente->id} ({$existente->nome} <{$existente->email}>).";
        if ($campo === 'e-mail') {
            $mensagem = "E-mail duplicado ao {$acao} \"{$nome}\" (Tutory id {$idTutory}). "
                ."Já existe aluno id {$existente->id} ({$existente->nome} <{$existente->email}>).";
        }
        $this->log($mensagem);
    }

    private function log(string $message): void
    {
        ($this->logger)($message);
        try {
            Log::info('[tutory:sincronizar-alunos] '.$message);
        } catch (Throwable) {
            // Logger do comando já recebeu a mensagem.
        }
    }
}
