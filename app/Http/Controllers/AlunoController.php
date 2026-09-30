<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlunoController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));
        $alunos = Aluno::query()
            ->comNomeParecido($busca)
            ->ordenadoNoRelatorio()
            ->get();

        if ($request->ajax()) {
            return response()->view('admin.alunos._linhas', compact('alunos', 'busca'));
        }

        return view('admin.alunos.index', compact('alunos', 'busca'));
    }

    public function export(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));
        $alunos = Aluno::query()
            ->comNomeParecido($busca)
            ->ordenadoNoRelatorio()
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Nome', 'E-mail', 'Telefone', 'Recebe e-mail', 'Status', 'Constância', 'Questões', '% acertos', 'Assuntos']);

        foreach ($alunos as $aluno) {
            fputcsv($handle, [
                $aluno->nome,
                $aluno->email,
                $aluno->telefone ?? '',
                $aluno->recebe_email ? 'Sim' : 'Não',
                $aluno->ativo ? 'Ativo' : 'Inativo',
                $aluno->last_performance ?? '',
                $aluno->last_question_volume ?? '',
                $aluno->last_accuracy_rate ?? '',
                $aluno->last_subjects ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $nomeArquivo = 'alunos_'.now()->format('Y-m-d_His').'.csv';

        return response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename='.$nomeArquivo,
        ]);
    }

    public function create()
    {
        return view('admin.alunos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('alunos', 'nome')],
            'email' => 'required|email|max:255|unique:alunos,email',
            'telefone' => ['nullable', 'string', 'max:50'],
            'recebe_email' => 'sometimes|boolean',
        ]);

        $data['telefone'] = $this->telefoneOuNulo($data['telefone'] ?? null);
        $data['recebe_email'] = $request->boolean('recebe_email');

        Aluno::create($data);

        return redirect()->route('alunos.index')->with('success', 'Aluno criado com sucesso.');
    }

    public function edit(Aluno $aluno)
    {
        return view('admin.alunos.edit', compact('aluno'));
    }

    public function update(Request $request, Aluno $aluno)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('alunos', 'nome')->ignore($aluno->id)],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('alunos', 'email')->ignore($aluno->id),
            ],
            'telefone' => ['nullable', 'string', 'max:50'],
            'recebe_email' => 'sometimes|boolean',
        ]);

        $data['telefone'] = $this->telefoneOuNulo($data['telefone'] ?? null);
        $data['recebe_email'] = $request->boolean('recebe_email');

        $aluno->update($data);

        return redirect()->route('alunos.index')->with('success', 'Aluno atualizado com sucesso.');
    }

    private function telefoneOuNulo(mixed $telefone): ?string
    {
        $telefone = trim((string) $telefone);

        return $telefone === '' ? null : $telefone;
    }

    public function destroy(Aluno $aluno)
    {
        $aluno->delete();

        return redirect()->route('alunos.index')->with('success', 'Aluno deletado com sucesso.');
    }
}
