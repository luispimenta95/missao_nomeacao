<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('alunos', 'acao_resolvida')) {
            return;
        }

        DB::table('alunos')->where('acao_resolvida', 'em_dia')->update([
            'acao_resolvida' => 'ok',
        ]);
    }

    public function down(): void
    {
        // O valor antigo em_dia não se distingue dos registros Ok gravados depois.
    }
};
