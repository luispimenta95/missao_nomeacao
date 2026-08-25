<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use Dompdf\Dompdf;
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

        $css = '';
        foreach (self::arquivosInter() as $peso => $arquivo) {
            $path = $dir.DIRECTORY_SEPARATOR.$arquivo;
            if (! is_file($path)) {
                continue;
            }
            $rel = 'resources/fonts/inter/'.$arquivo;
            $css .= "@font-face{font-family:'Inter';font-style:normal;font-weight:{$peso};src:url('{$rel}') format('truetype');}";
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

    /**
     * Fonte efetiva do canvas/Dompdf. Inter entra pelo @font-face;
     * se não registrar, o fallback precisa ser DejaVu Sans — nunca Times.
     */
    public static function defaultFontDompdf(?string $chave = null): string
    {
        $chave = self::resolver($chave);
        $familia = self::OPCOES[$chave]['familia'];
        if ($chave === 'inter' || strcasecmp($familia, 'Inter') === 0) {
            return 'DejaVu Sans';
        }

        return $familia;
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
        $options->set('defaultFont', self::defaultFontDompdf($chave));
        $chroot = array_values(array_filter([
            function_exists('base_path') ? base_path() : null,
            function_exists('public_path') ? public_path() : null,
        ], static fn ($p) => is_string($p) && is_dir($p)));
        if ($chroot !== []) {
            $options->setChroot($chroot);
        }

        return $options;
    }

    /**
     * Garante que o HTML resolva as TTFs relativas ao chroot e tenta
     * registrar Inter no FontMetrics (canvas + fallback sem Times).
     */
    public static function aplicarNoDompdf(Dompdf $dompdf): void
    {
        try {
            if (function_exists('base_path')) {
                $dompdf->setBasePath(base_path());
            }
        } catch (Throwable) {
            // Base path é opcional; @font-face relativo ainda pode resolver pelo chroot.
        }

        $dir = self::diretorioInter();
        if ($dir === null) {
            return;
        }

        try {
            $metrics = $dompdf->getFontMetrics();
        } catch (Throwable) {
            return;
        }

        $pesos = [
            400 => ['weight' => 'normal', 'style' => 'normal'],
            500 => ['weight' => 500, 'style' => 'normal'],
            600 => ['weight' => 600, 'style' => 'normal'],
            700 => ['weight' => 'bold', 'style' => 'normal'],
        ];
        foreach (self::arquivosInter() as $peso => $arquivo) {
            $path = $dir.DIRECTORY_SEPARATOR.$arquivo;
            if (! is_file($path) || ! isset($pesos[$peso])) {
                continue;
            }
            $abs = realpath($path);
            if (! is_string($abs) || $abs === '') {
                continue;
            }
            try {
                $metrics->registerFont([
                    'family' => 'Inter',
                    'weight' => $pesos[$peso]['weight'],
                    'style' => $pesos[$peso]['style'],
                ], 'file://'.$abs);
            } catch (Throwable) {
                // Sem Inter registrado, defaultFont DejaVu Sans cobre o corpo.
            }
        }
    }

    public static function normalizar(?string $chave): string
    {
        $chave = strtolower(trim((string) $chave));
        if ($chave === '' || ! self::ehValida($chave)) {
            return self::PADRAO;
        }

        return $chave;
    }

    /**
     * @return array<int, string>
     */
    private static function arquivosInter(): array
    {
        return [
            400 => 'Inter-Regular.ttf',
            500 => 'Inter-Medium.ttf',
            600 => 'Inter-SemiBold.ttf',
            700 => 'Inter-Bold.ttf',
        ];
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
