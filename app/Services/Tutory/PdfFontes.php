<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use Dompdf\Options;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fontes do PDF. Inter é a identidade do relatório quinzenal;
 * DejaVu permanece como fallback (acentos + Base-14).
 */
final class PdfFontes
{
    public const PADRAO = 'inter';

    public const CONFIG_CHAVE = 'pdf_fonte';

    /**
     * @var array<string, array{nome: string, familia: string, css: string, web: string, descricao: string, acentos: bool}>
     */
    public const OPCOES = [
        'inter' => [
            'nome' => 'Inter',
            'familia' => 'Inter',
            'css' => "'Inter', 'DejaVu Sans', Helvetica, sans-serif",
            'web' => 'Inter, Arial, Helvetica, sans-serif',
            'descricao' => 'Identidade do relatório quinzenal. Boa leitura em números, tabelas e textos pequenos; cobre português (ã, ç, á).',
            'acentos' => true,
        ],
        'dejavu' => [
            'nome' => 'DejaVu Sans',
            'familia' => 'DejaVu Sans',
            'css' => "'DejaVu Sans', Helvetica, sans-serif",
            'web' => 'Arial, Helvetica, sans-serif',
            'descricao' => 'Fallback. Cobre português (ã, ç, á) e é embutida no arquivo — abre igual no Adobe, Chrome, Preview, iOS e Android.',
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
        if (! self::ehValida($chave)) {
            $chave = self::PADRAO;
        }
        if ($chave === 'inter' && self::diretorioInter() === null) {
            return 'dejavu';
        }

        return $chave;
    }

    public static function fontFaceCss(?string $chave = null): string
    {
        $chave = self::resolver($chave);
        if ($chave !== 'inter') {
            return '';
        }
        $dir = self::diretorioInter();
        if ($dir === null) {
            return '';
        }

        $pesos = [
            400 => 'Inter-Regular.ttf',
            500 => 'Inter-Medium.ttf',
            600 => 'Inter-SemiBold.ttf',
            700 => 'Inter-Bold.ttf',
        ];
        $css = '';
        foreach ($pesos as $peso => $arquivo) {
            $path = $dir.DIRECTORY_SEPARATOR.$arquivo;
            if (! is_file($path)) {
                continue;
            }
            $url = str_replace('\\', '/', (string) realpath($path));
            $css .= "@font-face{font-family:'Inter';font-style:normal;font-weight:{$peso};src:url('file://{$url}') format('truetype');}";
        }

        return $css;
    }

    public static function diretorioInter(): ?string
    {
        $dir = function_exists('base_path')
            ? base_path('resources/fonts/inter')
            : dirname(__DIR__, 3).'/resources/fonts/inter';
        if (! is_dir($dir) || ! is_file($dir.'/Inter-Regular.ttf') || ! is_file($dir.'/Inter-Bold.ttf')) {
            return null;
        }

        return $dir;
    }

    public static function familiaDompdf(?string $chave = null): string
    {
        $chave = self::resolver($chave);

        return self::OPCOES[$chave]['familia'];
    }

    public static function css(?string $chave = null): string
    {
        $chave = self::resolver($chave);

        return self::OPCOES[$chave]['css'];
    }

    public static function cssWeb(?string $chave = null): string
    {
        $chave = self::resolver($chave);

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

    private static function resolver(?string $chave): string
    {
        if ($chave !== null && trim($chave) !== '') {
            $normalizada = self::normalizar($chave);
            if ($normalizada === 'inter' && self::diretorioInter() === null) {
                return 'dejavu';
            }

            return $normalizada;
        }

        return self::chaveAtual();
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
