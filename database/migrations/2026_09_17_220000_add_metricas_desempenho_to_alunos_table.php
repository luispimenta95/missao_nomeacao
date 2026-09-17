<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('last_question_volume')->nullable()->after('last_performance');
            $table->string('last_accuracy_rate')->nullable()->after('last_question_volume');
            $table->string('last_subjects')->nullable()->after('last_accuracy_rate');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn([
                'last_question_volume',
                'last_accuracy_rate',
                'last_subjects',
            ]);
        });
    }
};
