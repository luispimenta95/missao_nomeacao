<?php

namespace App\Http\Controllers;

use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use Illuminate\Http\Request;

class DesempenhoAdminController extends Controller
{
    public function index()
    {
        $eixos = EixoDesempenho::query()
            ->with(['faixas' => static fn ($q) => $q->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        return view('admin.desempenho.index', compact('eixos'));
    }

    public function edit(FaixaDesempenho $desempenho)
    {
        $desempenho->load('eixo');

        return view('admin.desempenho.edit', [
            'faixa' => $desempenho,
        ]);
    }

    public function update(Request $request, FaixaDesempenho $desempenho)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'valor_min' => 'nullable|numeric',
            'valor_max' => 'nullable|numeric',
            'ordem' => 'required|integer|min:1|max:999',
            'texto_email' => 'required|string|max:8000',
            'ativo' => 'sometimes|boolean',
        ]);

        if (
            isset($data['valor_min'], $data['valor_max'])
            && $data['valor_min'] !== null
            && $data['valor_max'] !== null
            && (float) $data['valor_min'] > (float) $data['valor_max']
        ) {
            return back()->withErrors([
                'valor_max' => 'O valor máximo deve ser maior ou igual ao mínimo.',
            ])->withInput();
        }

        $desempenho->update([
            'nome' => $data['nome'],
            'valor_min' => $data['valor_min'] ?? null,
            'valor_max' => $data['valor_max'] ?? null,
            'ordem' => $data['ordem'],
            'texto_email' => $data['texto_email'],
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('desempenho.index')->with('success', 'Faixa de desempenho atualizada.');
    }
}
