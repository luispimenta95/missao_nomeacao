<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eixos_desempenho', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 64)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('faixas_desempenho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eixo_desempenho_id')
                ->constrained('eixos_desempenho')
                ->cascadeOnDelete();
            $table->string('codigo', 64);
            $table->string('nome');
            $table->decimal('valor_min', 12, 2)->nullable();
            $table->decimal('valor_max', 12, 2)->nullable();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->text('texto_email');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['eixo_desempenho_id', 'codigo'], 'faixas_eixo_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faixas_desempenho');
        Schema::dropIfExists('eixos_desempenho');
    }
};
