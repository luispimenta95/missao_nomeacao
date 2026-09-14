<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('tutory_id')->nullable()->unique()->after('id');
            $table->unique('nome');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropUnique(['nome']);
            $table->dropUnique(['tutory_id']);
            $table->dropColumn('tutory_id');
        });
    }
};
