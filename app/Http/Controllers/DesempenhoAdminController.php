<?php

namespace App\Http\Controllers;

use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use App\Models\TextoFaixaDesempenho;
use Illuminate\Http\Request;

class DesempenhoAdminController extends Controller
{
    public function index()
    {
        $eixos = EixoDesempenho::query()
            ->with(['faixas' => static fn ($q) => $q->withCount([
                'textos as textos_ativos_count' => static fn ($textos) => $textos->where('ativo', true),
            ])->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        return view('admin.desempenho.index', compact('eixos'));
    }

    public function edit(FaixaDesempenho $desempenho)
    {
        $desempenho->load(['eixo', 'textos']);

        return view('admin.desempenho.edit', [
            'faixa' => $desempenho,
            'textoPrincipal' => $desempenho->textos->firstWhere('canonico', true)?->texto ?? $desempenho->texto_email,
            'alternativos' => $desempenho->textos
                ->where('canonico', false)
                ->where('ativo', true)
                ->map(static fn (TextoFaixaDesempenho $texto): array => [
                    'id' => $texto->id,
                    'texto' => $texto->texto,
                ])
                ->values(),
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
            'alternativos' => 'nullable|array|max:20',
            'alternativos.*.id' => 'nullable|integer',
            'alternativos.*.texto' => 'nullable|string|max:8000',
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

        $this->sincronizarTextos($desempenho, $data['texto_email'], $data['alternativos'] ?? []);

        return redirect()->route('desempenho.index')->with('success', 'Faixa de desempenho atualizada.');
    }

    /**
     * @param  list<array<string, mixed>>  $alternativos
     */
    private function sincronizarTextos(FaixaDesempenho $faixa, string $textoPrincipal, array $alternativos): void
    {
        $canonico = $faixa->garantirTextoCanonico();
        $canonico->update([
            'texto' => $textoPrincipal,
            'ordem' => 1,
            'ativo' => true,
        ]);

        $mantidos = [];
        $ordem = 2;
        foreach ($alternativos as $item) {
            if (! is_array($item)) {
                continue;
            }
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($texto === '') {
                continue;
            }

            $id = isset($item['id']) && $item['id'] !== '' && $item['id'] !== null
                ? (int) $item['id']
                : null;
            $existente = $id === null
                ? null
                : $faixa->textos()->where('id', $id)->where('canonico', false)->first();

            if ($existente !== null) {
                $existente->update([
                    'texto' => $texto,
                    'ordem' => $ordem,
                    'ativo' => true,
                ]);
                $mantidos[] = $existente->id;
            } else {
                $novo = $faixa->textos()->create([
                    'texto' => $texto,
                    'ordem' => $ordem,
                    'canonico' => false,
                    'ativo' => true,
                ]);
                $mantidos[] = $novo->id;
            }
            $ordem++;
        }

        $sobrando = $faixa->textos()->where('canonico', false);
        if ($mantidos !== []) {
            $sobrando->whereNotIn('id', $mantidos);
        }
        $sobrando->get()->each(function (TextoFaixaDesempenho $texto): void {
            if ($texto->usos()->exists()) {
                $texto->update(['ativo' => false]);

                return;
            }
            $texto->delete();
        });
    }
}
