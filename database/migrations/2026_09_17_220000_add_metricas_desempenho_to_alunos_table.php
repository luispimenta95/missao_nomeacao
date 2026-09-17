<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('last_volume_questoes')->nullable()->after('last_performance');
            $table->string('last_percentual_acertos')->nullable()->after('last_volume_questoes');
            $table->string('last_assuntos')->nullable()->after('last_percentual_acertos');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn([
                'last_volume_questoes',
                'last_percentual_acertos',
                'last_assuntos',
            ]);
        });
    }
};
