<?php

namespace App\Console\Commands;

use App\Models\RelatorioPdfPeriodo;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use Illuminate\Console\Command;

class LiberarPeriodosPdfCommand extends Command
{
    protected $signature = 'tutory:liberar-periodos-pdf';

    protected $description = 'No dia 16 insere o período 1 do mês atual; no dia 1 insere o período 2 do mês anterior. Remove o que sair da janela (2 × N meses).';

    public function handle(RelatorioPeriodoCatalog $catalog): int
    {
        $tz = (string) config('app.timezone');
        $agora = now()->timezone($tz);
        $meses = RelatorioPeriodoCatalog::mesesVisiveis();
        $limite = RelatorioPeriodoCatalog::limiteLinhas($meses);
        $this->line('Agora: '.$agora->toDateTimeString().' ('.$tz.')');
        $this->line('Janela: '.$meses.' meses · até '.$limite.' períodos no combo');

        $quinzena = $catalog->periodoDaQuinzena($agora);
        if ($quinzena !== null) {
            $this->line('Quinzena de hoje: '.$quinzena['chave'].' · '.$quinzena['label']);
        } else {
            $this->comment('Fora dos dias 1 e 16 — só sincroniza a janela (insert + delete).');
        }

        $antes = RelatorioPdfPeriodo::query()->count();
        $resultado = $catalog->sincronizar($agora);
        $total = RelatorioPdfPeriodo::query()->count();

        $this->info(
            'Inseridos: '.$resultado['inseridos']
            .' | Removidos: '.$resultado['removidos']
            .' | Total na tabela: '.$total
            .' (antes: '.$antes.')'
        );

        foreach ($catalog->listar($agora) as $item) {
            $this->line('  '.$item['chave'].' · '.$item['label']);
        }

        return self::SUCCESS;
    }
}
