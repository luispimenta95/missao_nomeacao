<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use Dompdf\Options;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fontes padrão do PDF (só famílias do Dompdf / Base-14).
 */
final class PdfFontes
{
    public const PADRAO = 'dejavu';

    public const CONFIG_CHAVE = 'pdf_fonte';

    /**
     * @var array<string, array{nome: string, familia: string, css: string, web: string, descricao: string, acentos: bool}>
     */
    public const OPCOES = [
        'dejavu' => [
            'nome' => 'DejaVu Sans',
            'familia' => 'DejaVu Sans',
            'css' => "'DejaVu Sans', Helvetica, sans-serif",
            'web' => 'Arial, Helvetica, sans-serif',
            'descricao' => 'Recomendada. Cobre português (ã, ç, á) e é embutida no arquivo — abre igual no Adobe, Chrome, Preview, iOS e Android.',
            'acentos' => true,
        ],
        'helvetica' => [
            'nome' => 'Helvetica',
            'familia' => 'Helvetica',
            'css' => 'Helvetica, Arial, sans-serif',
            'web' => 'Helvetica, Arial, sans-serif',
            'descricao' => 'Fonte padrão do PDF (Base-14). Sempre disponível, mas não tem ã/ç.',
            'acentos' => false,
        ],
        'times' => [
            'nome' => 'Times',
            'familia' => 'Times-Roman',
            'css' => "Times, 'Times New Roman', serif",
            'web' => "'Times New Roman', Times, serif",
            'descricao' => 'Serifada clássica (Base-14). Sem ã/ç.',
            'acentos' => false,
        ],
        'courier' => [
            'nome' => 'Courier',
            'familia' => 'Courier',
            'css' => 'Courier, monospace',
            'web' => "'Courier New', Courier, monospace",
            'descricao' => 'Monoespaçada (Base-14). Sem ã/ç.',
            'acentos' => false,
        ],
    ];

    /**
     * @return array<string, array{nome: string, familia: string, css: string, web: string, descricao: string, acentos: bool}>
     */
    public static function opcoes(): array
    {
        return self::OPCOES;
    }

    public static function ehValida(?string $chave): bool
    {
        return is_string($chave) && isset(self::OPCOES[strtolower($chave)]);
    }

    public static function chaveAtual(): string
    {
        $salva = self::chaveSalva();
        $env = strtolower(trim((string) env('TUTORY_PDF_FONT', '')));
        $chave = strtolower(trim($salva !== null && $salva !== '' ? $salva : ($env !== '' ? $env : self::PADRAO)));

        return self::ehValida($chave) ? $chave : self::PADRAO;
    }

    public static function familiaDompdf(?string $chave = null): string
    {
        $chave = self::normalizar($chave);

        return self::OPCOES[$chave]['familia'];
    }

    public static function css(?string $chave = null): string
    {
        $chave = self::normalizar($chave);

        return self::OPCOES[$chave]['css'];
    }

    public static function cssWeb(?string $chave = null): string
    {
        $chave = self::normalizar($chave);

        return self::OPCOES[$chave]['web'];
    }

    public static function opcoesDompdf(?string $chave = null): Options
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', self::familiaDompdf($chave));
        $chroot = array_values(array_filter([
            function_exists('base_path') ? base_path() : null,
            function_exists('public_path') ? public_path() : null,
        ], static fn ($p) => is_string($p) && is_dir($p)));
        if ($chroot !== []) {
            $options->setChroot($chroot);
        }

        return $options;
    }

    public static function normalizar(?string $chave): string
    {
        $chave = strtolower(trim((string) $chave));
        if ($chave === '' || ! self::ehValida($chave)) {
            return self::PADRAO;
        }

        return $chave;
    }

    private static function chaveSalva(): ?string
    {
        try {
            if (! class_exists(Configuracao::class) || ! Schema::hasTable('configuracoes')) {
                return null;
            }

            return Configuracao::valor(self::CONFIG_CHAVE);
        } catch (Throwable) {
            return null;
        }
    }
}
