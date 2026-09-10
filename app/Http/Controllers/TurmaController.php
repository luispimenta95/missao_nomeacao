<?php

namespace App\Http\Controllers;

use App\Http\Requests\TurmaRequest;
use App\Models\Turma;
use Illuminate\Support\Str;

class TurmaController extends Controller
{
    public function index()
    {
        $turmas = Turma::query()
            ->orderByDesc('ativo')
            ->orderBy('ordem_exibicao')
            ->orderBy('title')
            ->get();

        return view('admin.turmas.index', compact('turmas'));
    }

    public function create()
    {
        return view('admin.turmas.create', [
            'turma' => new Turma([
                'ativo' => true,
                'exibir_no_site' => true,
                'aceitar_novos_alunos' => true,
                'grupo_exibicao' => Turma::GRUPO_TURMA_DIRECIONADA,
                'acao_principal' => Turma::ACAO_CHECKOUT,
                'ordem_exibicao' => (int) Turma::max('ordem_exibicao') + 1,
            ]),
        ]);
    }

    public function store(TurmaRequest $request)
    {
        $data = $request->payload();
        $data['logo_path'] = $this->storeLogo($request, $data['title']);

        Turma::create($data);

        return redirect()->route('turmas.index')->with('success', 'Turma criada. As alterações passam a valer no site Missão Nomeação.');
    }

    public function show(Turma $turma)
    {
        return view('admin.turmas.show', compact('turma'));
    }

    public function edit(Turma $turma)
    {
        return view('admin.turmas.edit', compact('turma'));
    }

    public function update(TurmaRequest $request, Turma $turma)
    {
        $data = $request->payload();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->storeLogo($request, $data['title']);
        }

        $turma->update($data);

        return redirect()->route('turmas.index')->with('success', 'Turma atualizada. O site Missão Nomeação já reflete essa configuração.');
    }

    public function destroy(Turma $turma)
    {
        $turma->delete();

        return redirect()->route('turmas.index')->with('success', 'Turma deletada com sucesso.');
    }

    private function storeLogo(TurmaRequest $request, string $title): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        $file = $request->file('logo');
        $filename = Str::slug($title).'-'.time().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('turmas', $filename, 'public');
    }
}
