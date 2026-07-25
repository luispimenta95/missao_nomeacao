<?php

namespace Database\Seeders;

use App\Models\CriterioDesempenho;
use App\Models\NivelDesempenho;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NiveisDesempenhoSeeder extends Seeder
{
    /**
     * Níveis em cascata (ordem menor = avaliado primeiro).
     * Critérios alinhados ao Excelente cadastrado pelo mentor:
     * horas brutas, horas líquidas, dias, semanas, % questões.
     */
    public function run(): void
    {
        $this->call(CriteriosDesempenhoSeeder::class);

        $ids = CriterioDesempenho::query()
            ->pluck('id', 'codigo');

        $obrigatorios = ['horas_brutas', 'horas_liquidas', 'dias', 'semanas', 'pct_questoes'];
        foreach ($obrigatorios as $codigo) {
            if (! isset($ids[$codigo])) {
                throw new \RuntimeException("Critério '{$codigo}' não encontrado. Rode CriteriosDesempenhoSeeder.");
            }
        }

        $niveis = [
            [
                'nome' => 'Excelente',
                'slug' => 'excelente',
                'ordem' => 1,
                'texto_email' => 'Seu desempenho foi excelente!',
                'regras' => [
                    ['codigo' => 'horas_brutas', 'operador' => '>', 'valor_min' => 600],
                    ['codigo' => 'horas_liquidas', 'operador' => '>', 'valor_min' => 520],
                    ['codigo' => 'dias', 'operador' => '>', 'valor_min' => 120],
                    ['codigo' => 'semanas', 'operador' => '>', 'valor_min' => 30],
                    ['codigo' => 'pct_questoes', 'operador' => '>', 'valor_min' => 90],
                ],
            ],
            [
                'nome' => 'Ótimo',
                'slug' => 'otimo',
                'ordem' => 2,
                'texto_email' => 'Seu desempenho foi ótimo! Continue com esse ritmo.',
                'regras' => [
                    ['codigo' => 'horas_brutas', 'operador' => '>', 'valor_min' => 450],
                    ['codigo' => 'horas_liquidas', 'operador' => '>', 'valor_min' => 380],
                    ['codigo' => 'dias', 'operador' => '>', 'valor_min' => 90],
                    ['codigo' => 'semanas', 'operador' => '>', 'valor_min' => 22],
                    ['codigo' => 'pct_questoes', 'operador' => '>', 'valor_min' => 80],
                ],
            ],
            [
                'nome' => 'Bom',
                'slug' => 'bom',
                'ordem' => 3,
                'texto_email' => 'Seu desempenho foi bom. Há espaço para evoluir ainda mais.',
                'regras' => [
                    ['codigo' => 'horas_brutas', 'operador' => '>', 'valor_min' => 300],
                    ['codigo' => 'horas_liquidas', 'operador' => '>', 'valor_min' => 250],
                    ['codigo' => 'dias', 'operador' => '>', 'valor_min' => 60],
                    ['codigo' => 'semanas', 'operador' => '>', 'valor_min' => 15],
                    ['codigo' => 'pct_questoes', 'operador' => '>', 'valor_min' => 70],
                ],
            ],
            [
                'nome' => 'Regular',
                'slug' => 'regular',
                'ordem' => 4,
                'texto_email' => 'Seu desempenho foi regular. Vamos reforçar a rotina de estudos.',
                'regras' => [
                    ['codigo' => 'horas_brutas', 'operador' => '>', 'valor_min' => 150],
                    ['codigo' => 'horas_liquidas', 'operador' => '>', 'valor_min' => 120],
                    ['codigo' => 'dias', 'operador' => '>', 'valor_min' => 30],
                    ['codigo' => 'semanas', 'operador' => '>', 'valor_min' => 8],
                    ['codigo' => 'pct_questoes', 'operador' => '>', 'valor_min' => 55],
                ],
            ],
            [
                'nome' => 'Requer atenção',
                'slug' => 'requer-atencao',
                'ordem' => 5,
                'texto_email' => 'Seu desempenho requer atenção. Vamos ajustar o plano e retomar o foco.',
                // Catch-all: quem não entrou nos níveis acima
                'regras' => [
                    ['codigo' => 'horas_brutas', 'operador' => '>=', 'valor_min' => 0],
                    ['codigo' => 'horas_liquidas', 'operador' => '>=', 'valor_min' => 0],
                    ['codigo' => 'dias', 'operador' => '>=', 'valor_min' => 0],
                    ['codigo' => 'semanas', 'operador' => '>=', 'valor_min' => 0],
                    ['codigo' => 'pct_questoes', 'operador' => '>=', 'valor_min' => 0],
                ],
            ],
        ];

        DB::transaction(function () use ($niveis, $ids): void {
            foreach ($niveis as $def) {
                $nivel = NivelDesempenho::query()
                    ->where('slug', $def['slug'])
                    ->orWhere('nome', $def['nome'])
                    ->first();

                if ($nivel === null) {
                    $nivel = NivelDesempenho::create([
                        'nome' => $def['nome'],
                        'slug' => $def['slug'],
                        'ordem' => $def['ordem'],
                        'texto_email' => $def['texto_email'],
                        'ativo' => true,
                    ]);
                } else {
                    $nivel->update([
                        'nome' => $def['nome'],
                        'slug' => $def['slug'],
                        'ordem' => $def['ordem'],
                        'texto_email' => $def['texto_email'],
                        'ativo' => true,
                    ]);
                }

                $nivel->regras()->delete();
                foreach ($def['regras'] as $regra) {
                    $nivel->regras()->create([
                        'criterio_desempenho_id' => $ids[$regra['codigo']],
                        'operador' => $regra['operador'],
                        'valor_min' => $regra['valor_min'],
                        'valor_max' => null,
                    ]);
                }
            }
        });
    }
}
