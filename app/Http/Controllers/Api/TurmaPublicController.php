<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurmaPublicResource;
use App\Models\Turma;
use Illuminate\Http\Request;

class TurmaPublicController extends Controller
{
    public function index(Request $request)
    {
        $pagina = $request->query('pagina', 'turmas-abertas');

        $query = Turma::query()->ordenado();

        $query = match ($pagina) {
            'mentoria' => $query->naMentoria(),
            default => $query->nasTurmasAbertas(),
        };

        if ($request->boolean('destacadas')) {
            $query->where('destacar_turmas_abertas', true);
        }

        if ($categoria = $request->query('categoria')) {
            $query->where('categoria_navegacao', $categoria);
        }

        if ($grupo = $request->query('grupo')) {
            $query->where('grupo_exibicao', $grupo);
        }

        if ($busca = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome_publico', 'like', "%{$busca}%")
                    ->orWhere('title', 'like', "%{$busca}%")
                    ->orWhere('description', 'like', "%{$busca}%")
                    ->orWhere('termos_busca', 'like', "%{$busca}%")
                    ->orWhere('orgao', 'like', "%{$busca}%")
                    ->orWhere('cargo', 'like', "%{$busca}%");
            });
        }

        $turmas = $query->get();

        return TurmaPublicResource::collection($turmas)->additional([
            'meta' => [
                'pagina' => $pagina === 'mentoria' ? 'mentoria' : 'turmas-abertas',
                'categorias' => $turmas->pluck('categoria_navegacao')->filter()->unique()->values(),
                'secoes' => $turmas->map->secaoPaginaPublica()->unique()->values(),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $turma = Turma::query()
            ->publicasNoSite()
            ->where('slug', $slug)
            ->firstOrFail();

        return new TurmaPublicResource($turma);
    }
}
