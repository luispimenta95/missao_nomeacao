<?php

namespace App\Http\Controllers;

use App\Models\CriterioDesempenho;
use App\Models\NivelDesempenho;
use App\Models\RegraDesempenho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DesempenhoAdminController extends Controller
{
    public function index()
    {
        $niveis = NivelDesempenho::query()
            ->withCount('regras')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        $criterios = CriterioDesempenho::query()->orderBy('ordem')->get();

        return view('admin.desempenho.index', compact('niveis', 'criterios'));
    }

    public function create()
    {
        $criterios = CriterioDesempenho::query()->orderBy('ordem')->get();
        $operadores = RegraDesempenho::OPERADORES;

        return view('admin.desempenho.create', compact('criterios', 'operadores'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data): void {
            $nivel = NivelDesempenho::create([
                'nome' => $data['nome'],
                'slug' => $this->slugUnico($data['nome']),
                'ordem' => $data['ordem'],
                'texto_email' => $data['texto_email'],
                'ativo' => $data['ativo'],
            ]);

            $this->sincronizarRegras($nivel, $data['regras']);
        });

        return redirect()->route('desempenho.index')->with('success', 'Nível de desempenho criado com sucesso.');
    }

    public function edit(NivelDesempenho $desempenho)
    {
        $desempenho->load('regras');
        $criterios = CriterioDesempenho::query()->orderBy('ordem')->get();
        $operadores = RegraDesempenho::OPERADORES;

        return view('admin.desempenho.edit', [
            'nivel' => $desempenho,
            'criterios' => $criterios,
            'operadores' => $operadores,
        ]);
    }

    public function update(Request $request, NivelDesempenho $desempenho)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $desempenho): void {
            $desempenho->update([
                'nome' => $data['nome'],
                'ordem' => $data['ordem'],
                'texto_email' => $data['texto_email'],
                'ativo' => $data['ativo'],
            ]);

            $desempenho->regras()->delete();
            $this->sincronizarRegras($desempenho, $data['regras']);
        });

        return redirect()->route('desempenho.index')->with('success', 'Nível de desempenho atualizado com sucesso.');
    }

    public function destroy(NivelDesempenho $desempenho)
    {
        $desempenho->delete();

        return redirect()->route('desempenho.index')->with('success', 'Nível de desempenho removido.');
    }

    /**
     * @return array{nome: string, ordem: int, texto_email: string, ativo: bool, regras: list<array<string, mixed>>}
     */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'ordem' => 'required|integer|min:1|max:999',
            'texto_email' => 'required|string|max:5000',
            'ativo' => 'sometimes|boolean',
            'regras' => 'required|array|min:1',
            'regras.*.criterio_desempenho_id' => [
                'required',
                'integer',
                Rule::exists('criterios_desempenho', 'id'),
            ],
            'regras.*.operador' => ['required', Rule::in(RegraDesempenho::OPERADORES)],
            'regras.*.valor_min' => 'nullable|numeric',
            'regras.*.valor_max' => 'nullable|numeric',
        ], [
            'regras.required' => 'Cadastre ao menos um critério para o nível.',
            'regras.min' => 'Cadastre ao menos um critério para o nível.',
        ]);

        $data['ativo'] = $request->boolean('ativo');

        $ids = [];
        foreach ($data['regras'] as $i => $regra) {
            $cid = (int) $regra['criterio_desempenho_id'];
            if (isset($ids[$cid])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "regras.{$i}.criterio_desempenho_id" => 'Não repita o mesmo critério no nível.',
                ]);
            }
            $ids[$cid] = true;

            $op = $regra['operador'];
            $min = $regra['valor_min'] ?? null;
            $max = $regra['valor_max'] ?? null;

            if ($op === 'between') {
                if ($min === null || $max === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "regras.{$i}.valor_min" => 'Para "entre", informe valor mínimo e máximo.',
                    ]);
                }
                if ((float) $min > (float) $max) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "regras.{$i}.valor_max" => 'O valor máximo deve ser maior ou igual ao mínimo.',
                    ]);
                }
            } elseif ($min === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "regras.{$i}.valor_min" => 'Informe o valor do critério.',
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $regras
     */
    private function sincronizarRegras(NivelDesempenho $nivel, array $regras): void
    {
        foreach ($regras as $regra) {
            $nivel->regras()->create([
                'criterio_desempenho_id' => (int) $regra['criterio_desempenho_id'],
                'operador' => $regra['operador'],
                'valor_min' => $regra['valor_min'] ?? null,
                'valor_max' => ($regra['operador'] ?? '') === 'between' ? ($regra['valor_max'] ?? null) : null,
            ]);
        }
    }

    private function slugUnico(string $nome): string
    {
        $base = Str::slug($nome) ?: 'nivel';
        $slug = $base;
        $n = 1;
        while (NivelDesempenho::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
