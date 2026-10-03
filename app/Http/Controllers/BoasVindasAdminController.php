<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Services\Alunos\TextoBoasVindasNovato;
use Illuminate\Http\Request;

class BoasVindasAdminController extends Controller
{
    public function edit()
    {
        return view('admin.boas-vindas.edit', [
            'texto' => Configuracao::valor(TextoBoasVindasNovato::CHAVE, TextoBoasVindasNovato::padrao()),
        ]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'texto' => ['required', 'string', 'max:8000'],
        ], [
            'texto.required' => 'Informe o texto de boas-vindas.',
        ]);

        Configuracao::definir(TextoBoasVindasNovato::CHAVE, trim($dados['texto']));

        return redirect()
            ->route('boas-vindas.edit')
            ->with('success', 'Texto de boas-vindas atualizado.');
    }
}
