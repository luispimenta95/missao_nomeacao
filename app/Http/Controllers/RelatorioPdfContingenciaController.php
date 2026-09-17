<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Services\Tutory\RelatorioPdfContingencia;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class RelatorioPdfContingenciaController extends Controller
{
    public function index(RelatorioPeriodoCatalog $catalog)
    {
        $catalog->sincronizar();
        $meses = RelatorioPeriodoCatalog::mesesVisiveis();

        $alunos = Aluno::query()
            ->whereNotNull('tutory_id')
            ->where('tutory_id', '!=', '')
            ->orderBy('nome')
            ->get();

        return view('admin.relatorios-pdf-contingencia.index', [
            'alunos' => $alunos,
            'periodos' => $catalog->listar(),
            'mesesVisiveis' => $meses,
            'limiteLinhas' => RelatorioPeriodoCatalog::limiteLinhas($meses),
            'progressToken' => (string) Str::uuid(),
        ]);
    }

    public function update(Request $request, RelatorioPeriodoCatalog $catalog)
    {
        $data = $request->validate([
            'meses' => [
                'required',
                'integer',
                'min:'.RelatorioPeriodoCatalog::MESES_MIN,
                'max:'.RelatorioPeriodoCatalog::MESES_MAX,
            ],
        ], [
            'meses.required' => 'Informe a quantidade de meses.',
            'meses.min' => 'Use pelo menos 1 mês.',
            'meses.max' => 'Use no máximo 24 meses.',
        ]);

        Configuracao::definir(RelatorioPeriodoCatalog::CONFIG_CHAVE, (string) $data['meses']);
        $catalog->sincronizar();
        $linhas = RelatorioPeriodoCatalog::limiteLinhas((int) $data['meses']);

        return redirect()
            ->route('relatorios-pdf-contingencia.index')
            ->with('success', 'Janela atualizada: o combo mostra até '.$linhas.' períodos (2 × '.$data['meses'].' meses).');
    }

    public function progresso(Request $request)
    {
        $token = (string) $request->query('token', '');
        if (! $this->tokenValido($token)) {
            return response()->json(['status' => 'idle', 'step' => 0, 'message' => ''], 422);
        }

        return response()->json($this->lerProgresso($token));
    }

    public function gerar(Request $request, RelatorioPeriodoCatalog $catalog, RelatorioPdfContingencia $contingencia)
    {
        if (is_array($request->input('aluno_id'))) {
            return $this->falha($request, ['aluno_id' => 'Gere o PDF de um aluno por vez.']);
        }

        $chaves = $catalog->chaves();
        $data = $request->validate([
            'aluno_id' => [
                'required',
                'integer',
                Rule::exists('alunos', 'id')->where(static function ($query): void {
                    $query->whereNotNull('tutory_id')->where('tutory_id', '!=', '');
                }),
            ],
            'periodo' => ['required', 'string', Rule::in($chaves)],
            'progress_token' => ['nullable', 'string', 'uuid'],
        ], [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.exists' => 'Aluno inválido ou sem vínculo com a Tutory.',
            'periodo.required' => 'Selecione o mês/período.',
            'periodo.in' => 'Este período ainda não está disponível.',
        ]);

        $periodo = $catalog->encontrar((string) $data['periodo']);
        if ($periodo === null) {
            return $this->falha($request, ['periodo' => 'Este período ainda não está disponível.']);
        }

        $aluno = Aluno::query()->findOrFail($data['aluno_id']);
        $token = (string) ($data['progress_token'] ?? '');
        $this->gravarProgresso($token, 1, 'Conectando à Tutory…', 'running');
        $this->liberarSessao();

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $logger = function (string $message) use ($token): void {
            $this->gravarProgresso($token, $this->etapaDoLog($message), $message, 'running');
        };

        try {
            $caminho = $contingencia->gerar($aluno, $periodo, $logger);
        } catch (Throwable $exc) {
            $this->gravarProgresso($token, 0, $exc->getMessage(), 'error');

            return $this->falha($request, ['gerar' => 'Não foi possível gerar o PDF: '.$exc->getMessage()]);
        }

        if (! is_string($caminho) || ! is_file($caminho)) {
            $this->gravarProgresso($token, 0, 'Arquivo do PDF não encontrado.', 'error');

            return $this->falha($request, ['gerar' => 'O PDF foi gerado, mas o arquivo não foi encontrado.']);
        }

        $this->gravarProgresso($token, 4, 'PDF pronto para download.', 'done');
        $nome = basename($caminho);

        return response()->download($caminho, $nome, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function falha(Request $request, array $errors, int $status = 422)
    {
        $mensagem = (string) collect($errors)->flatten()->first();
        if ($this->querJson($request)) {
            return response()->json([
                'message' => $mensagem,
                'errors' => $errors,
            ], $status);
        }

        return redirect()
            ->route('relatorios-pdf-contingencia.index')
            ->withInput()
            ->withErrors($errors);
    }

    private function querJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || str_contains(strtolower((string) $request->header('Accept')), 'application/json');
    }

    private function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token);
    }

    private function chaveProgresso(string $token): string
    {
        return 'pdf-contingencia-progress.'.$token;
    }

    /**
     * @return array{status: string, step: int, message: string}
     */
    private function lerProgresso(string $token): array
    {
        $padrao = ['status' => 'idle', 'step' => 0, 'message' => 'Aguardando…'];
        if (! $this->tokenValido($token)) {
            return $padrao;
        }
        $salvo = Cache::get($this->chaveProgresso($token));

        return is_array($salvo) ? array_merge($padrao, $salvo) : $padrao;
    }

    private function gravarProgresso(string $token, int $step, string $message, string $status): void
    {
        if (! $this->tokenValido($token)) {
            return;
        }
        $atual = $this->lerProgresso($token);
        Cache::put($this->chaveProgresso($token), [
            'status' => $status,
            'step' => max((int) ($atual['step'] ?? 0), $step),
            'message' => $message,
        ], now()->addMinutes(30));
    }

    private function etapaDoLog(string $message): int
    {
        $m = mb_strtolower($message);
        if (str_contains($m, 'salvo') || str_contains($m, 'sucesso') || str_contains($m, 'pronto')) {
            return 4;
        }
        if (str_contains($m, 'dompdf') || str_contains($m, 'puppeteer') || str_contains($m, 'consolidado')) {
            return 3;
        }
        if (str_contains($m, 'login realizado') || str_contains($m, 'gerando') || str_contains($m, 'datas:') || str_contains($m, '[rodada')) {
            return 2;
        }

        return 1;
    }

    private function liberarSessao(): void
    {
        try {
            session()->save();
        } catch (Throwable) {
        }
        if (function_exists('session_write_close')) {
            session_write_close();
        }
    }
}
