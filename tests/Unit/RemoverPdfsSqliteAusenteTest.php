<?php

namespace Tests\Unit;

use App\Services\Tutory\CoachReportDownloader;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

class RemoverPdfsSqliteAusenteTest extends TestCase
{
    public function test_preserva_pdfs_quando_o_sqlite_nao_existe(): void
    {
        $pasta = sys_get_temp_dir().'/tutory-pdfs-'.uniqid('', true);
        mkdir($pasta, 0775, true);
        $pdf = $pasta.'/relatorio_consolidado_20260815_1200_Giovanna_1.pdf';
        file_put_contents($pdf, '%PDF-1.4 fake');

        $logs = [];
        $downloader = new CoachReportDownloader('1', static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });
        $ref = new ReflectionClass($downloader);
        $ref->getProperty('pastaDownload')->setValue($downloader, $pasta);

        $original = config('database.connections.sqlite.database');
        $missing = sys_get_temp_dir().'/mn-missing-'.uniqid('', true).'.sqlite';
        $connection = config('database.default');
        $pdo = RefreshDatabaseState::$inMemoryConnections[$connection] ?? null;

        try {
            config(['database.connections.sqlite.database' => $missing]);
            DB::purge($connection);

            $ref->getMethod('enviarEmailsDosAlunos')->invoke($downloader);
        } finally {
            config(['database.connections.sqlite.database' => $original]);
            DB::purge($connection);
            if ($pdo) {
                DB::connection($connection)->setPdo($pdo);
            }
            @unlink($pdf);
            @rmdir($pasta);
        }

        $this->assertTrue(
            collect($logs)->contains(static fn (string $l): bool => str_contains($l, 'PDFs preservados'))
        );
    }
}
