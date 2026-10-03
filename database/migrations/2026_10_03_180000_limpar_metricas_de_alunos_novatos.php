<?php

use App\Models\Configuracao;
use App\Services\Alunos\LimpezaMetricasNovato;
use App\Services\Alunos\TextoBoasVindasNovato;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Configuracao::query()->where('chave', TextoBoasVindasNovato::CHAVE)->exists()) {
            Configuracao::definir(TextoBoasVindasNovato::CHAVE, TextoBoasVindasNovato::padrao());
        }

        (new LimpezaMetricasNovato)->executar();
    }

    public function down(): void
    {
        Configuracao::query()->where('chave', TextoBoasVindasNovato::CHAVE)->delete();
    }
};
