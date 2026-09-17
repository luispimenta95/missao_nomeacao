<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorio_pdf_periodos', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7);
            $table->string('period', 1);
            $table->string('label');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['year_month', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorio_pdf_periodos');
    }
};
