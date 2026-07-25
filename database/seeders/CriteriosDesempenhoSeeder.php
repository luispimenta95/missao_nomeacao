<?php

namespace Database\Seeders;

use App\Models\CriterioDesempenho;
use Illuminate\Database\Seeder;

class CriteriosDesempenhoSeeder extends Seeder
{
    public function run(): void
    {
        $criterios = [
            ['codigo' => 'horas_brutas', 'nome' => 'Horas brutas', 'unidade' => 'h', 'ordem' => 1],
            ['codigo' => 'horas_liquidas', 'nome' => 'Horas líquidas', 'unidade' => 'h', 'ordem' => 2],
            ['codigo' => 'dias', 'nome' => 'Dias', 'unidade' => 'd', 'ordem' => 3],
            ['codigo' => 'semanas', 'nome' => 'Semanas', 'unidade' => 'sem', 'ordem' => 4],
            ['codigo' => 'pct_questoes', 'nome' => '% questões', 'unidade' => '%', 'ordem' => 5],
        ];

        foreach ($criterios as $criterio) {
            CriterioDesempenho::query()->updateOrCreate(
                ['codigo' => $criterio['codigo']],
                $criterio
            );
        }
    }
}
