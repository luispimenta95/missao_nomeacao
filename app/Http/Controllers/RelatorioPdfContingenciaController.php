<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Services\Tutory\RelatorioPdfContingencia;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class RelatorioPdfContingenciaController extends Controller
{
    public function index(RelatorioPeriodoCatalog $catalog)
    {
        $catalog->sincronizar();

        $alunos = Aluno::query()
            ->whereNotNull('tutory_id')
            ->where('tutory_id', '!=', '')
            ->orderBy('nome')
            ->get();

        return view('admin.relatorios-pdf-contingencia.index', [
            'alunos' => $alunos,
            'periodos' => $catalog->listar(),
        ]);
    }

    public function gerar(Request $request, RelatorioPeriodoCatalog $catalog, RelatorioPdfContingencia $contingencia)
    {
        if (is_array($request->input('aluno_id'))) {
            return redirect()
                ->route('relatorios-pdf-contingencia.index')
                ->withErrors(['aluno_id' => 'Gere o PDF de um aluno por vez.']);
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
        ], [
            'aluno_id.required' => 'Selecione um aluno.',
            'aluno_id.exists' => 'Aluno inválido ou sem vínculo com a Tutory.',
            'periodo.required' => 'Selecione o mês/período.',
            'periodo.in' => 'Este período ainda não está disponível.',
        ]);

        $periodo = $catalog->encontrar((string) $data['periodo']);
        if ($periodo === null) {
            return redirect()
                ->route('relatorios-pdf-contingencia.index')
                ->withErrors(['periodo' => 'Este período ainda não está disponível.']);
        }

        $aluno = Aluno::query()->findOrFail($data['aluno_id']);

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        try {
            $caminho = $contingencia->gerar($aluno, $periodo);
        } catch (Throwable $exc) {
            return redirect()
                ->route('relatorios-pdf-contingencia.index')
                ->withInput()
                ->withErrors(['gerar' => 'Não foi possível gerar o PDF: '.$exc->getMessage()]);
        }

        if (! is_string($caminho) || ! is_file($caminho)) {
            return redirect()
                ->route('relatorios-pdf-contingencia.index')
                ->withInput()
                ->withErrors(['gerar' => 'O PDF foi gerado, mas o arquivo não foi encontrado.']);
        }

        $nome = basename($caminho);

        return response()->download($caminho, $nome, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
