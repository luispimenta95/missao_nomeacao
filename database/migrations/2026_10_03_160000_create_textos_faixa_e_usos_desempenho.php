<?php

use App\Models\FaixaDesempenho;
use App\Services\Desempenho\RegistroInicialTextoFaixa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textos_faixa_desempenho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faixa_desempenho_id')
                ->constrained('faixas_desempenho')
                ->cascadeOnDelete();
            $table->text('texto');
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->boolean('canonico')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['faixa_desempenho_id', 'ativo', 'ordem'], 'textos_faixa_ordem_idx');
        });

        Schema::create('usos_texto_desempenho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')
                ->constrained('alunos')
                ->cascadeOnDelete();
            $table->foreignId('faixa_desempenho_id')
                ->constrained('faixas_desempenho')
                ->cascadeOnDelete();
            $table->foreignId('texto_faixa_desempenho_id')
                ->constrained('textos_faixa_desempenho')
                ->restrictOnDelete();
            $table->string('eixo_codigo', 64);
            $table->unsignedInteger('ciclo')->default(1);
            $table->string('periodo', 32)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['aluno_id', 'faixa_desempenho_id', 'ciclo'], 'usos_texto_ciclo_idx');
            $table->index(['aluno_id', 'faixa_desempenho_id', 'periodo'], 'usos_texto_periodo_idx');
        });

        foreach (FaixaDesempenho::query()->cursor() as $faixa) {
            $faixa->garantirTextoCanonico();
        }

        app(RegistroInicialTextoFaixa::class)->registrarTodos();
    }

    public function down(): void
    {
        Schema::dropIfExists('usos_texto_desempenho');
        Schema::dropIfExists('textos_faixa_desempenho');
    }
};
