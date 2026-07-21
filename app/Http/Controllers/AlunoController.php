<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlunoController extends Controller
{
    public function index()
    {
        $alunos = Aluno::orderBy('nome')->get();

        return view('admin.alunos.index', compact('alunos'));
    }

    public function create()
    {
        return view('admin.alunos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:alunos,email',
            'recebe_email' => 'sometimes|boolean',
        ]);

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
            'nome' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('alunos', 'email')->ignore($aluno->id),
            ],
            'recebe_email' => 'sometimes|boolean',
        ]);

        $data['recebe_email'] = $request->boolean('recebe_email');

        $aluno->update($data);

        return redirect()->route('alunos.index')->with('success', 'Aluno atualizado com sucesso.');
    }

    public function destroy(Aluno $aluno)
    {
        $aluno->delete();

        return redirect()->route('alunos.index')->with('success', 'Aluno deletado com sucesso.');
    }
}
