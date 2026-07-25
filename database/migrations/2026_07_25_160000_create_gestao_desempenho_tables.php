<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_desempenho', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 64)->unique();
            $table->string('nome');
            $table->string('unidade', 16)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('niveis_desempenho', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->text('texto_email');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('regras_desempenho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nivel_desempenho_id')
                ->constrained('niveis_desempenho')
                ->cascadeOnDelete();
            $table->foreignId('criterio_desempenho_id')
                ->constrained('criterios_desempenho')
                ->restrictOnDelete();
            $table->string('operador', 16);
            $table->decimal('valor_min', 12, 2)->nullable();
            $table->decimal('valor_max', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['nivel_desempenho_id', 'criterio_desempenho_id'], 'regras_nivel_criterio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regras_desempenho');
        Schema::dropIfExists('niveis_desempenho');
        Schema::dropIfExists('criterios_desempenho');
    }
};
