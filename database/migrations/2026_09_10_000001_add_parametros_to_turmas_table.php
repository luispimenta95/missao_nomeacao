<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->string('nome_publico')->nullable()->after('slug');
            $table->boolean('ativo')->default(true)->after('status');
            $table->boolean('exibir_no_site')->default(true)->after('ativo');
            $table->boolean('plano_pronto_tutory')->default(false)->after('exibir_no_site');
            $table->boolean('aceitar_novos_alunos')->default(true)->after('plano_pronto_tutory');
            $table->string('grupo_exibicao')->default('turma_direcionada')->after('aceitar_novos_alunos');
            $table->string('categoria_navegacao')->nullable()->after('grupo_exibicao');
            $table->string('orgao')->nullable()->after('categoria_navegacao');
            $table->string('cargo')->nullable()->after('orgao');
            $table->text('termos_busca')->nullable()->after('cargo');
            $table->string('momento_concurso')->nullable()->after('termos_busca');
            $table->boolean('exibir_momento_concurso')->default(false)->after('momento_concurso');
            $table->string('acao_principal')->default('checkout')->after('exibir_momento_concurso');
            $table->string('whatsapp_url', 500)->nullable()->after('checkout_url');
            $table->string('interesse_url', 500)->nullable()->after('whatsapp_url');
            $table->json('popup_opcoes')->nullable()->after('interesse_url');
            $table->boolean('destacar_turmas_abertas')->default(false)->after('popup_opcoes');
            $table->boolean('exibir_na_mentoria')->default(false)->after('destacar_turmas_abertas');
            $table->unsignedInteger('ordem_exibicao')->default(0)->after('exibir_na_mentoria');
            $table->string('secao_pagina')->nullable()->after('ordem_exibicao');
            $table->string('texto_cta')->nullable()->after('secao_pagina');
        });

        $turmas = DB::table('turmas')->orderBy('id')->get();

        foreach ($turmas as $index => $turma) {
            $slug = Str::slug((string) $turma->title) ?: 'turma-'.$turma->id;
            $exists = DB::table('turmas')->where('slug', $slug)->where('id', '!=', $turma->id)->exists();
            if ($exists) {
                $slug .= '-'.$turma->id;
            }

            DB::table('turmas')->where('id', $turma->id)->update([
                'slug' => $slug,
                'nome_publico' => $turma->title,
                'ativo' => true,
                'exibir_no_site' => $turma->status === 'aberta',
                'aceitar_novos_alunos' => $turma->status === 'aberta',
                'exibir_na_mentoria' => $turma->status === 'aberta',
                'destacar_turmas_abertas' => $turma->status === 'aberta',
                'ordem_exibicao' => $index + 1,
            ]);
        }

        Schema::table('turmas', function (Blueprint $table) {
            $table->unique('slug');
            $table->index(['ativo', 'exibir_no_site', 'ordem_exibicao'], 'turmas_site_ordem_idx');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex('turmas_site_ordem_idx');

            $table->dropColumn([
                'slug',
                'nome_publico',
                'ativo',
                'exibir_no_site',
                'plano_pronto_tutory',
                'aceitar_novos_alunos',
                'grupo_exibicao',
                'categoria_navegacao',
                'orgao',
                'cargo',
                'termos_busca',
                'momento_concurso',
                'exibir_momento_concurso',
                'acao_principal',
                'whatsapp_url',
                'interesse_url',
                'popup_opcoes',
                'destacar_turmas_abertas',
                'exibir_na_mentoria',
                'ordem_exibicao',
                'secao_pagina',
                'texto_cta',
            ]);
        });
    }
};
