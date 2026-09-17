<?php

namespace App\Console\Commands;

use App\Models\RelatorioPdfPeriodo;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use Illuminate\Console\Command;

class LiberarPeriodosPdfCommand extends Command
{
    protected $signature = 'tutory:liberar-periodos-pdf';

    protected $description = 'Adiciona os períodos de PDF já geráveis (P1 a partir do dia 16; P2 a partir do dia 1 do mês seguinte)';

    public function handle(RelatorioPeriodoCatalog $catalog): int
    {
        $tz = (string) config('app.timezone');
        $agora = now()->timezone($tz);
        $this->line('Agora: '.$agora->toDateTimeString().' ('.$tz.')');

        $antes = RelatorioPdfPeriodo::query()->count();
        $novos = $catalog->sincronizar($agora);
        $total = RelatorioPdfPeriodo::query()->count();

        if ($novos === 0) {
            $this->info("Nenhum período novo. Total na tabela: {$total}");
        } else {
            $this->info("Adicionados {$novos} período(s). Total na tabela: {$total} (antes: {$antes})");
        }

        foreach ($catalog->listar($agora) as $item) {
            $this->line('  '.$item['chave'].' · '.$item['label']);
        }

        return self::SUCCESS;
    }
}
