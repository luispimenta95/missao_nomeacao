<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('last_performance_codigo')->nullable()->after('last_performance');
            $table->string('last_question_volume_codigo')->nullable()->after('last_question_volume');
            $table->string('last_accuracy_rate_codigo')->nullable()->after('last_accuracy_rate');
            $table->string('prev_performance')->nullable()->after('last_subjects');
            $table->string('prev_performance_codigo')->nullable()->after('prev_performance');
            $table->string('prev_question_volume')->nullable()->after('prev_performance_codigo');
            $table->string('prev_question_volume_codigo')->nullable()->after('prev_question_volume');
            $table->string('prev_accuracy_rate')->nullable()->after('prev_question_volume_codigo');
            $table->string('prev_accuracy_rate_codigo')->nullable()->after('prev_accuracy_rate');
            $table->string('metricas_periodo')->nullable()->after('prev_accuracy_rate_codigo');
            $table->json('assuntos_detalhe')->nullable()->after('metricas_periodo');
            $table->timestamp('ultimo_contato_em')->nullable()->after('assuntos_detalhe');
            $table->text('ultima_observacao')->nullable()->after('ultimo_contato_em');
            $table->date('proximo_contato_em')->nullable()->after('ultima_observacao');
            $table->string('acao_resolvida')->nullable()->after('proximo_contato_em');
            $table->string('acao_resolvida_assinatura', 64)->nullable()->after('acao_resolvida');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn([
                'last_performance_codigo',
                'last_question_volume_codigo',
                'last_accuracy_rate_codigo',
                'prev_performance',
                'prev_performance_codigo',
                'prev_question_volume',
                'prev_question_volume_codigo',
                'prev_accuracy_rate',
                'prev_accuracy_rate_codigo',
                'metricas_periodo',
                'assuntos_detalhe',
                'ultimo_contato_em',
                'ultima_observacao',
                'proximo_contato_em',
                'acao_resolvida',
                'acao_resolvida_assinatura',
            ]);
        });
    }
};
