<?php

namespace App\Console\Commands;

use App\Services\Tutory\SincronizarAlunosTutory;
use Illuminate\Console\Command;
use Throwable;

class SincronizarAlunosTutoryCommand extends Command
{
    protected $signature = 'tutory:sincronizar-alunos';

    protected $description = 'Entra na Tutory, pesquisa alunos ativos e cadastra/atualiza a tabela alunos (recebe_email=true)';

    public function handle(): int
    {
        $this->info('Sincronizando alunos ativos da Tutory...');

        try {
            $sync = new SincronizarAlunosTutory(
                logger: function (string $message): void {
                    $this->line($message);
                },
            );
            $resultado = $sync->run();
            $this->info(
                "Pronto. criados={$resultado['criados']} atualizados={$resultado['atualizados']} "
                ."inalterados={$resultado['inalterados']} pulados={$resultado['pulados']}"
            );

            return self::SUCCESS;
        } catch (Throwable $exc) {
            $this->error($exc->getMessage());

            return self::FAILURE;
        }
    }
}
