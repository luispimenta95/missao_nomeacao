<?php

/**
 * Baixa os relatórios do Coach no Tutory e envia por e-mail aos alunos cadastrados.
 *
 * 1. Login (/intent/login) → sessão + Bearer
 * 2. /alunos/consulta?status=ativos (cards com data-id)
 * 3. Para cada modelo em RELATORIOS[]:
 *    POST /intent/cadastrar-relatorio-coach (agrupamento=dia)
 *    GET /documentos/relatorios/{model}?key=...
 *    PDF oficial via Puppeteer + PDFWriter/jsPDF (fallback Dompdf só em questões)
 * 4. Reprocessa falhas (até 3x) por aluno+modelo
 * 5. Lista alunos do banco → localiza PDFs → um e-mail com todos os anexos se recebe_email
 */

namespace App\Services\Tutory;

use App\Http\Util\MailHelper;
use App\Models\Aluno;
use App\Services\Desempenho\AvaliadorDesempenho;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CoachReportDownloader
{
    private const BASE = 'https://admin.tutory.com.br';

    private const ALUNA_TESTE = 'Laíra Lacerda';

    private const MAX_TENTATIVAS = 3;

    /**
     * Modelos de relatório a baixar (mesmo fluxo para cada índice).
     * model = segmento em /documentos/relatorios/{model}
     *
     * @var list<array{model: string, nome: string, slug: string}>
     */
    private const RELATORIOS = [
        [
            'model' => 'questoes',
            'nome' => 'Desempenho em Questões',
            'slug' => 'questoes',
        ],
        [
            'model' => 'progresso',
            'nome' => 'Progresso do plano',
            'slug' => 'progresso',
        ],
    ];

    private string $urlLogin;

    private string $email;

    private string $senha;

    private string $pastaDownload;

    private int $timeout;

    private string $periodo;

    private bool $teste;

    private string $cookieFile;

    private ?FileCookieJar $cookieJar = null;

    private ?string $bearerToken = null;

    /** @var list<string> */
    private array $endpointsGeracao = [];

    /** @var callable(string): void */
    private $logger;

    public function __construct(
        string $periodo,
        bool $teste = false,
        ?callable $logger = null,
    ) {
        $this->periodo = $periodo;
        $this->teste = $teste;
        $this->logger = $logger ?? static function (string $message): void {
            echo $message . PHP_EOL;
        };

        $loginUrl = trim((string) env('LOGIN_URL', ''));
        $this->urlLogin = $loginUrl !== '' ? $loginUrl : 'https://admin.tutory.com.br/login';
        // TEMP (teste): credenciais hardcoded — trocar por .env depois
        $this->senha = '05473793150';
        $this->email = 'missaonomeacao';
        $pastaEnv = trim((string) env('PASTA_DOWNLOAD', ''));
        $defaultPasta = function_exists('public_path')
            ? public_path('pdfs')
            : (function_exists('storage_path')
                ? storage_path('app/tutory-relatorios')
                : rtrim((string) getenv('HOME'), '/') . '/Relatorios_Tutory');
        $this->pastaDownload = $this->expandHome($pastaEnv !== '' ? $pastaEnv : $defaultPasta);

        $this->timeout = (int) (env('TIMEOUT') ?: 120);
        $this->cookieFile = sys_get_temp_dir() . '/tutory_cookies_' . getmypid() . '.json';
        $this->cookieJar = new FileCookieJar($this->cookieFile, true);

        if (! is_dir($this->pastaDownload)) {
            mkdir($this->pastaDownload, 0775, true);
        }
    }

    public function run(): int
    {
        $this->validarConfig();

        try {
            $this->login();
            $this->baixarTodos();
            $this->enviarEmailsDosAlunos();

            return 0;
        } catch (Throwable $exc) {
            $this->log('ERRO FATAL:');
            $this->log((string) $exc);
            throw $exc;
        } finally {
            if (is_file($this->cookieFile)) {
                @unlink($this->cookieFile);
            }
        }
    }

    private function log(string $message): void
    {
        ($this->logger)($message);
    }

    private function validarConfig(): void
    {
        // TEMP: credenciais hardcoded para teste — validação de .env desativada
        if (! in_array($this->periodo, ['1', '2'], true)) {
            throw new RuntimeException('--periodo deve ser 1 ou 2.');
        }
    }

    private function expandHome(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return rtrim((string) getenv('HOME'), '/') . substr($path, 1);
        }

        return $path;
    }

    private function client(): PendingRequest
    {
        $req = Http::withOptions([
            'cookies' => $this->cookieJar,
            'allow_redirects' => true,
            'timeout' => $this->timeout,
            'connect_timeout' => 20,
            'http_errors' => false,
        ])
            ->withHeaders([
                'User-Agent' => 'MissaoNomeacao-TutoryCLI/2.0',
                'Accept' => 'application/json, text/html, */*;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => self::BASE,
                'Referer' => self::BASE . '/alunos/consulta',
            ])
            ->baseUrl(self::BASE);

        if ($this->bearerToken !== null && $this->bearerToken !== '') {
            $req = $req->withToken($this->bearerToken);
        }

        return $req;
    }

    private function login(): void
    {
        $this->log('Abrindo página de login...');
        $loginPage = $this->client()->get($this->urlLogin);
        if ($loginPage->status() >= 400) {
            throw new RuntimeException('Não foi possível abrir a página de login (HTTP ' . $loginPage->status() . ').');
        }

        $this->log('Enviando credenciais para /intent/login...');
        $response = $this->client()
            ->asForm()
            ->withHeaders(['Referer' => $this->urlLogin])
            ->post('/intent/login', [
                'account' => $this->email,
                'password' => $this->senha,
            ]);

        $json = $response->json();
        if (! is_array($json) || empty($json['result'])) {
            $erro = is_array($json) ? (string) ($json['error'] ?? 'login falhou') : $response->body();
            throw new RuntimeException('Falha no login: ' . $erro);
        }

        $index = $this->client()
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get('/index');
        $this->bearerToken = $this->extrairToken($index->body());
        if ($this->bearerToken === null) {
            // Fallback: algumas contas só embutem adminUser na consulta
            $consulta = $this->client()
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->get('/alunos/consulta', ['status' => 'ativos']);
            $this->bearerToken = $this->extrairToken($consulta->body());
        }
        if ($this->bearerToken !== null) {
            $this->log('Login realizado (Bearer token obtido)');
        } else {
            $this->log('Login realizado (sessão por cookie; token Bearer não encontrado — intents autenticadas podem falhar)');
        }
    }

    private function extrairToken(string $html): ?string
    {
        if (preg_match('/adminUser\s*=\s*\{(.*?)\}\s*;/s', $html, $m)) {
            // Formato do painel: token: '...'  (chave sem aspas)
            if (preg_match('/["\']?token["\']?\s*:\s*["\']([^"\']+)["\']/', $m[1], $t)) {
                return $t[1];
            }
        }
        if (preg_match('#["\']?token["\']?\s*:\s*["\']([A-Za-z0-9._\-+/=]+)["\']#', $html, $t)) {
            return $t[1];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} Y-m-d
     */
    private function datasPeriodoIso(): array
    {
        $hoje = new \DateTimeImmutable('now');
        if ($this->periodo === '1') {
            return [$hoje->format('Y-m-01'), $hoje->format('Y-m-15')];
        }
        $ultimo = (int) $hoje->format('t');

        return [$hoje->format('Y-m-16'), $hoje->format('Y-m-') . str_pad((string) $ultimo, 2, '0', STR_PAD_LEFT)];
    }

    /**
     * @return array{0: string, 1: string} d/m/Y (API Tutory)
     */
    private function datasPeriodoBr(): array
    {
        [$ini, $fim] = $this->datasPeriodoIso();

        return [
            \DateTimeImmutable::createFromFormat('Y-m-d', $ini)->format('d/m/Y'),
            \DateTimeImmutable::createFromFormat('Y-m-d', $fim)->format('d/m/Y'),
        ];
    }

    private function loadDom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new DOMXPath($dom);
    }

    private function hrefInutil(?string $href): bool
    {
        if ($href === null || $href === '') {
            return true;
        }
        $h = strtolower(trim($href));

        return $h === '#'
            || $h === '#!'
            || str_starts_with($h, '#')
            || str_starts_with($h, 'javascript:');
    }

    /**
     * @return list<array{nome: string, id: string}>
     */
    private function coletarAlunosAtivos(): array
    {
        $this->log("Abrindo /alunos/consulta com status=ativos...");
        $resp = $this->client()->get('/alunos/consulta', ['status' => 'ativos']);
        $html = $resp->body();
        if (! str_contains($html, 'pesquisa-aluno-container')) {
            $resp = $this->client()->asForm()->post('/alunos/consulta', ['status' => 'ativos']);
            $html = $resp->body();
        }
        if (! str_contains($html, 'pesquisa-aluno-container')) {
            throw new RuntimeException('Lista de alunos ativos não encontrada em /alunos/consulta.');
        }

        // Endpoints reais da página (ex.: /intent/cadastrar-relatorio-coach)
        if (preg_match_all('#/intent/[a-zA-Z0-9_./-]+#', $html, $m)) {
            $this->endpointsGeracao = array_values(array_unique($m[0]));
            $this->log('Intents na página: ' . implode(', ', $this->endpointsGeracao));
        }

        $alunos = [];
        $vistos = [];
        $pagina = 1;
        $urlAtual = self::BASE . '/alunos/consulta?status=ativos';

        while (true) {
            $paginaAlunos = $this->parseAlunosDaPagina($html);
            $this->log("Coletando página {$pagina}: " . count($paginaAlunos) . ' aluno(s)');
            foreach ($paginaAlunos as $aluno) {
                if ($aluno['id'] === '' || isset($vistos[$aluno['id']])) {
                    continue;
                }
                $vistos[$aluno['id']] = true;
                $alunos[] = $aluno;
            }

            $proxima = $this->proximaPaginaUrl($html, $urlAtual);
            if ($proxima === null) {
                break;
            }
            $this->log('Próxima página: ' . $proxima);
            $html = $this->client()->get($proxima)->body();
            $urlAtual = $proxima;
            $pagina++;
            if ($pagina > 100) {
                break;
            }
        }

        $this->log('Total de alunos ativos: ' . count($alunos));

        return $alunos;
    }

    /**
     * @return list<array{nome: string, id: string}>
     */
    private function parseAlunosDaPagina(string $html): array
    {
        $xp = $this->loadDom($html);
        $cards = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' pesquisa-aluno-container ')]");
        $alunos = [];
        if ($cards === false) {
            return [];
        }

        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }
            $nomeNodes = $xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pesquisa-aluno-nome ')]", $card);
            $nome = ($nomeNodes !== false && $nomeNodes->length > 0)
                ? trim((string) $nomeNodes->item(0)?->textContent)
                : '';
            if ($nome === '') {
                continue;
            }

            $id = '';
            $links = $xp->query(".//a[contains(concat(' ', normalize-space(@class), ' '), ' btn-generate-report ')]", $card);
            if ($links !== false && $links->length > 0 && $links->item(0) instanceof DOMElement) {
                $id = trim($links->item(0)->getAttribute('data-id'));
            }
            if ($id === '') {
                // fallback: value hidden / painel
                $vals = $xp->query('.//*[@value]', $card);
                if ($vals !== false) {
                    foreach ($vals as $el) {
                        if (! $el instanceof DOMElement) {
                            continue;
                        }
                        $v = trim($el->getAttribute('value'));
                        if (preg_match('/^\d{4,}$/', $v)) {
                            $id = $v;
                            break;
                        }
                    }
                }
            }
            if ($id === '') {
                $this->log("AVISO: sem data-id para {$nome} — ignorado");

                continue;
            }

            $alunos[] = ['nome' => $nome, 'id' => $id];
        }

        return $alunos;
    }

    private function proximaPaginaUrl(string $html, string $urlAtual): ?string
    {
        $xp = $this->loadDom($html);
        $queries = [
            "//li[contains(@class,'page-item') and not(contains(@class,'disabled'))]/a[@rel='next']",
            "//a[contains(@class,'page-link') and (@rel='next' or normalize-space()='›' or normalize-space()='»')]",
        ];
        foreach ($queries as $q) {
            $nodes = $xp->query($q);
            if ($nodes === false) {
                continue;
            }
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $href = trim($node->getAttribute('href'));
                if ($this->hrefInutil($href)) {
                    continue;
                }
                $abs = $this->absolutizar($href, $urlAtual);
                if ($abs !== $urlAtual) {
                    return $abs;
                }
            }
        }

        return null;
    }

    private function absolutizar(string $url, string $base = self::BASE): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            return self::BASE . $url;
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @return list<array{model: string, nome: string, slug: string}>
     */
    private function relatorios(): array
    {
        return self::RELATORIOS;
    }

    /**
     * @param  array{nome: string, id: string}  $aluno
     * @param  array{model: string, nome: string, slug: string}  $relatorio
     */
    private function processarAluno(array $aluno, array $relatorio): ?string
    {
        $nome = $aluno['nome'];
        $id = $aluno['id'];
        $model = $relatorio['model'];
        $rotulo = $relatorio['nome'];
        [$dtIniIso, $dtFimIso] = $this->datasPeriodoIso();
        [$dtIni, $dtFim] = $this->datasPeriodoBr();
        $this->log("[{$nome}] [{$rotulo}] id={$id} | Datas: {$dtIniIso} → {$dtFimIso} ({$dtIni} → {$dtFim})");

        $endpoint = trim((string) env('TUTORY_REPORT_GENERATE_URL', ''));
        if ($endpoint === '') {
            $endpoint = '/intent/cadastrar-relatorio-coach';
        }
        if ($this->endpointsGeracao !== [] && ! in_array($endpoint, $this->endpointsGeracao, true)) {
            foreach ($this->endpointsGeracao as $cand) {
                if (str_contains($cand, 'relatorio-coach') || str_contains($cand, 'cadastrar-relatorio')) {
                    $endpoint = $cand;
                    break;
                }
            }
        }

        $this->log("[{$nome}] [{$rotulo}] Gerando via {$endpoint}...");
        // PDF de referência do painel usa agrupamento diário (gráficos por dia)
        $agrupamento = trim((string) env('TUTORY_REPORT_AGRUPAMENTO', 'dia')) ?: 'dia';
        $body = 'alunos[]=' . rawurlencode($id)
            . '&dt_ini=' . rawurlencode($dtIni)
            . '&dt_fim=' . rawurlencode($dtFim)
            . '&agrupamento=' . rawurlencode($agrupamento);

        $resp = $this->client()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'])
            ->withBody($body, 'application/x-www-form-urlencoded; charset=UTF-8')
            ->post($endpoint);

        $json = $resp->json();
        if (! is_array($json) || empty($json['result'])) {
            $erro = is_array($json) ? (string) ($json['error'] ?? $resp->body()) : $resp->body();
            $this->log("[{$nome}] [{$rotulo}] Falha ao gerar: " . $erro);

            return null;
        }

        $lista = $json['data'] ?? null;
        if (! is_array($lista) || $lista === [] || empty($lista[0]['token'])) {
            $this->log("[{$nome}] [{$rotulo}] Resposta sem token de relatório: " . $resp->body());

            return null;
        }

        $key = (string) $lista[0]['token'];
        $reportUrl = self::BASE . '/documentos/relatorios/' . $model . '?key=' . rawurlencode($key);
        $this->log("[{$nome}] [{$rotulo}] Abrindo relatório (/documentos/relatorios/{$model})...");

        $destino = $this->caminhoDestinoPdf($nome, $model, $id);

        $pagina = $this->client()
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get('/documentos/relatorios/' . $model, ['key' => $key]);

        if ($pagina->status() >= 400 || ! str_contains($pagina->body(), 'btn_save')) {
            $this->log("[{$nome}] [{$rotulo}] Página do relatório inválida (HTTP {$pagina->status()})");

            return null;
        }

        $html = $pagina->body();
        if ($model === 'questoes' && (! str_contains($html, 'main-numbers') || ! str_contains($html, 'tabela_questoes'))) {
            $this->log("[{$nome}] [{$rotulo}] AVISO: página sem Breve Panorama ou Performance por assunto");
        }
        if ($model === 'progresso' && ! str_contains($html, 'chart_progresso_principal')) {
            $this->log("[{$nome}] [{$rotulo}] AVISO: página sem gráfico principal de progresso");
        }

        // Réplica do PDF do painel (mesmo PDFWriter/jsPDF do botão Baixar)
        if ($this->gerarPdfComPuppeteer($nome, $reportUrl, $destino, $model, $rotulo)) {
            if ($this->pdfContemSecoesObrigatorias($destino, $model)) {
                $this->salvarMetricasProgressoSeAplicavel($destino, $model, $html);

                return $destino;
            }
            $this->log("[{$nome}] [{$rotulo}] PDF Puppeteer incompleto — tentando Dompdf");
            @unlink($destino);
        }

        $this->log("[{$nome}] [{$rotulo}] Fallback Dompdf...");

        if ($model === 'progresso') {
            $salvo = $this->gerarPdfProgressoDoHtml($nome, $id, $html, $dtIniIso, $dtFimIso);
            if ($salvo !== null) {
                $this->salvarMetricasProgressoSeAplicavel($salvo, $model, $html);
            }

            return $salvo;
        }

        $salvo = $this->gerarPdfDoHtml($nome, $id, $html, $dtIniIso, $dtFimIso, $model);
        if ($salvo !== null) {
            $this->salvarMetricasProgressoSeAplicavel($salvo, $model, $html);
        }

        return $salvo;
    }

    private function pdfContemSecoesObrigatorias(string $caminho, string $model = 'questoes'): bool
    {
        if (! is_file($caminho) || filesize($caminho) < 500) {
            return false;
        }

        $bytes = file_get_contents($caminho);
        if ($bytes === false) {
            return false;
        }

        // Conteúdo oficial do painel vem do PDFWriter/jsPDF.
        // page.pdf() do Chromium altera gráficos (ex.: horas diárias agregadas) e não tem jsPDF.
        $temJsPdf = str_contains($bytes, 'jsPDF');
        $imagens = substr_count($bytes, '/Subtype /Image') + substr_count($bytes, '/Subtype/Image');

        if ($model === 'progresso') {
            $temTexto = str_contains($bytes, 'Progresso')
                || str_contains($bytes, 'Panorama')
                || str_contains($bytes, 'Motivação')
                || str_contains($bytes, 'Motivacao');

            return $temJsPdf && $temTexto && $imagens >= 5;
        }

        // jsPDF grava texto em literais PDF; aceita com ou sem acentos escapados
        $temPanorama = str_contains($bytes, 'Breve Panorama') || str_contains($bytes, 'Breve');
        $temAssuntos = str_contains($bytes, 'Performance por assunto')
            || str_contains($bytes, 'Performance por')
            || str_contains($bytes, 'tabela_questoes')
            || str_contains($bytes, 'Taxa de');

        return $temJsPdf && $temPanorama && $temAssuntos;
    }

    private function cookieHeader(): string
    {
        if ($this->cookieJar === null) {
            return '';
        }

        $parts = [];
        foreach ($this->cookieJar as $cookie) {
            $parts[] = $cookie->getName() . '=' . $cookie->getValue();
        }

        return implode('; ', $parts);
    }

    /**
     * Usa o PDFWriter/jsPDF do painel via Puppeteer → PDF idêntico ao "Baixar".
     */
    private function gerarPdfComPuppeteer(
        string $nome,
        string $reportUrl,
        string $destino,
        string $model = 'questoes',
        string $rotulo = 'Relatório',
    ): bool {
        $script = function_exists('base_path')
            ? base_path('scripts/tutory-render-pdf.mjs')
            : dirname(__DIR__, 3) . '/scripts/tutory-render-pdf.mjs';

        if (! is_file($script)) {
            $this->log("[{$nome}] [{$rotulo}] Script Puppeteer ausente: {$script}");

            return false;
        }

        $node = trim((string) env('NODE_BINARY', 'node')) ?: 'node';
        $cmd = [$node, $script, '--url', $reportUrl, '--out', $destino, '--model', $model];
        $cookie = $this->cookieHeader();
        if ($cookie !== '') {
            $cmd[] = '--cookie';
            $cmd[] = $cookie;
        }
        if ($this->bearerToken !== null && $this->bearerToken !== '') {
            $cmd[] = '--token';
            $cmd[] = $this->bearerToken;
        }

        $this->log("[{$nome}] [{$rotulo}] Renderizando PDF oficial (PDFWriter/jsPDF via Puppeteer)...");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = function_exists('base_path') ? base_path() : dirname($script, 2);
        $proc = @proc_open($cmd, $descriptors, $pipes, $cwd, null);
        if (! is_resource($proc)) {
            $this->log("[{$nome}] [{$rotulo}] Não foi possível iniciar Node/Puppeteer");

            return false;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0 || ! is_file($destino) || filesize($destino) < 500) {
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            $this->log("[{$nome}] [{$rotulo}] Puppeteer falhou (exit {$code}): " . $detail);

            return false;
        }

        $this->log("[{$nome}] [{$rotulo}] Arquivo salvo: {$destino}");

        return true;
    }

    private function caminhoDestinoPdf(string $nomeAluno, string $model = 'questoes', ?string $id = null): string
    {
        $seguro = $this->sanitizarNomeArquivo($nomeAluno);
        $modelSeguro = preg_replace('/[^a-z0-9\-]/i', '', $model) ?: 'relatorio';
        $data = date('Ymd_Hi');
        // Formato: relatorio_{model}_{Ymd_Hi}_{aluno}_{periodo}.pdf
        $destino = $this->pastaDownload.'/relatorio_'.$modelSeguro.'_'.$data.'_'.$seguro.'_'.$this->periodo.'.pdf';
        $n = 1;
        while (file_exists($destino)) {
            $destino = $this->pastaDownload.'/relatorio_'.$modelSeguro.'_'.$data.'_'.$seguro.'_'.$this->periodo.'_'.$n.'.pdf';
            $n++;
        }

        return $destino;
    }

    private function sanitizarNomeArquivo(string $nomeAluno): string
    {
        // Mantém acentos (Laíra, José, etc.); remove só caracteres inválidos para arquivo
        $seguro = preg_replace('/[^\p{L}\p{N} ._\\-]/u', '', $nomeAluno) ?? 'aluno';
        $seguro = preg_replace('/\s+/u', '_', trim($seguro)) ?? 'aluno';
        $seguro = preg_replace('/_+/u', '_', $seguro) ?? 'aluno';

        return trim($seguro, '._-') ?: 'aluno';
    }

    /**
     * Normaliza nome para comparação (sem acento/underscore/case).
     */
    private function normalizarParaComparacao(string $nome): string
    {
        $nome = mb_strtolower(str_replace('_', ' ', $nome));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        $nome = is_string($ascii) && $ascii !== '' ? $ascii : $nome;

        return preg_replace('/[^a-z0-9]+/', '', $nome) ?? '';
    }

    /**
     * Compara nomes do admin vs nome embutido no arquivo (aceita pequenas diferenças de digitação).
     */
    private function nomesArquivoSaoCompativeis(string $nomeAdmin, string $nomeArquivo): bool
    {
        if (strcasecmp($nomeAdmin, $nomeArquivo) === 0) {
            return true;
        }

        similar_text(mb_strtolower($nomeAdmin), mb_strtolower($nomeArquivo), $pct);
        if ($pct >= 85.0) {
            return true;
        }

        $a = $this->normalizarParaComparacao($nomeAdmin);
        $b = $this->normalizarParaComparacao($nomeArquivo);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        // Ex.: Larceda vs Lacerda (troca de letras)
        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen <= 0) {
            return false;
        }
        $dist = levenshtein($a, $b);
        $limite = $maxLen <= 8 ? 1 : 2;

        return $dist >= 0 && $dist <= $limite;
    }

    /**
     * Extrai modelo e nome do aluno em filenames conhecidos.
     *
     * @return array{model: string, nome: string}|null
     */
    private function extrairMetaDoArquivoPdf(string $arquivo): ?array
    {
        $periodo = preg_quote($this->periodo, '/');

        // relatorio_{model}_{Ymd}_{Hi}_{Nome}_{periodo}.pdf
        if (preg_match('/^relatorio_([a-z0-9\-]+)_\d{8}_\d{4}_(.+)_'.$periodo.'(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return ['model' => strtolower($m[1]), 'nome' => $m[2]];
        }
        // legado: relatorio_{Ymd}_{Hi}_{Nome}_{periodo}.pdf (questões)
        if (preg_match('/^relatorio_\d{8}_\d{4}_(.+)_'.$periodo.'(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return ['model' => 'questoes', 'nome' => $m[1]];
        }
        // legado: relatorio-{id}-{Nome}-{ddmmyyyy}.pdf
        if (preg_match('/^relatorio-\d+-(.+)-\d{8}(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return ['model' => 'questoes', 'nome' => $m[1]];
        }
        // legado: {Nome}_{Ym}.pdf
        if (preg_match('/^(.+)_\d{4}-\d{2}(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return ['model' => 'questoes', 'nome' => $m[1]];
        }

        return null;
    }

    /**
     * Localiza os PDFs mais recentes do aluno (um por modelo em RELATORIOS) na pasta de download.
     *
     * @return list<string>
     */
    private function encontrarPdfsAluno(string $nomeAluno): array
    {
        if (! is_dir($this->pastaDownload)) {
            return [];
        }

        $seguro = $this->sanitizarNomeArquivo($nomeAluno);
        $modelos = array_column($this->relatorios(), 'model');
        /** @var array<string, list<string>> $porModelo */
        $porModelo = [];

        foreach (scandir($this->pastaDownload) ?: [] as $arquivo) {
            if (! str_ends_with(mb_strtolower($arquivo), '.pdf')) {
                continue;
            }
            $meta = $this->extrairMetaDoArquivoPdf($arquivo);
            if ($meta === null) {
                continue;
            }
            if (! $this->nomesArquivoSaoCompativeis($seguro, $meta['nome'])) {
                continue;
            }
            if (! in_array($meta['model'], $modelos, true)) {
                continue;
            }
            $porModelo[$meta['model']][] = $this->pastaDownload.'/'.$arquivo;
        }

        $encontrados = [];
        foreach ($modelos as $model) {
            $candidatos = $porModelo[$model] ?? [];
            if ($candidatos === []) {
                continue;
            }
            usort($candidatos, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
            $encontrados[] = $candidatos[0];
        }

        return $encontrados;
    }

    /**
     * Lista alunos do admin e envia os PDFs por e-mail (todos os anexos em um único e-mail).
     */
    private function enviarEmailsDosAlunos(): void
    {
        $this->log(str_repeat('=', 50));
        $this->log('Enviando relatórios por e-mail (alunos cadastrados no admin)...');

        $query = Aluno::query()->orderBy('nome');
        $alunos = $query->get();
        if ($this->teste) {
            $alvo = mb_strtolower(self::ALUNA_TESTE);
            $alunos = $alunos
                ->filter(static fn (Aluno $a) => str_contains(mb_strtolower($a->nome), $alvo))
                ->values();
        }

        if ($alunos->isEmpty()) {
            $this->log('Nenhum aluno cadastrado no admin para envio.');

            return;
        }

        [$dtIni, $dtFim] = $this->datasPeriodoBr();
        $periodoLabel = $dtIni.' a '.$dtFim.' (período '.$this->periodo.')';
        $nomePorModelo = [];
        foreach ($this->relatorios() as $relatorio) {
            $nomePorModelo[$relatorio['model']] = $relatorio['nome'];
        }

        $enviados = 0;
        $pulados = 0;
        $falhas = 0;

        foreach ($alunos as $aluno) {
            $this->log("[{$aluno->nome}] e-mail={$aluno->email} | recebe_email=".($aluno->recebe_email ? 'sim' : 'não'));

            $pdfs = $this->encontrarPdfsAluno($aluno->nome);
            if ($pdfs === []) {
                $this->log("[{$aluno->nome}] Nenhum PDF encontrado em {$this->pastaDownload}");
                $this->log("[{$aluno->nome}] Dica: o nome no admin deve coincidir com o do Tutory (ex.: Laíra Lacerda).");
                $falhas++;

                continue;
            }

            $nomesAnexos = [];
            foreach ($pdfs as $pdf) {
                $this->log("[{$aluno->nome}] PDF: ".basename($pdf));
                $meta = $this->extrairMetaDoArquivoPdf(basename($pdf));
                if ($meta !== null && isset($nomePorModelo[$meta['model']])) {
                    $nomesAnexos[] = $nomePorModelo[$meta['model']];
                }
            }

            $modelosEsperados = array_column($this->relatorios(), 'model');
            $modelosEncontrados = [];
            foreach ($pdfs as $pdf) {
                $meta = $this->extrairMetaDoArquivoPdf(basename($pdf));
                if ($meta !== null) {
                    $modelosEncontrados[] = $meta['model'];
                }
            }
            $faltando = array_values(array_diff($modelosEsperados, $modelosEncontrados));
            if ($faltando !== []) {
                $this->log("[{$aluno->nome}] AVISO: faltam PDFs dos modelos: ".implode(', ', $faltando));
            }

            if (! $aluno->recebe_email) {
                $this->log("[{$aluno->nome}] E-mail não enviado (recebe_email=false)");
                $pulados++;

                continue;
            }

            if (! filter_var($aluno->email, FILTER_VALIDATE_EMAIL)) {
                $this->log("[{$aluno->nome}] E-mail inválido: {$aluno->email}");
                $falhas++;

                continue;
            }

            try {
                $avaliacao = $this->avaliarDesempenhoDoProgresso($pdfs);
                $nivelNome = $avaliacao['nivel']?->nome;
                $textoDesempenho = $avaliacao['nivel']?->texto_email;
                if ($nivelNome !== null) {
                    $this->log("[{$aluno->nome}] Desempenho: {$nivelNome}");
                } elseif ($avaliacao['motivo'] !== '') {
                    $this->log("[{$aluno->nome}] Desempenho: {$avaliacao['motivo']}");
                }

                MailHelper::emailRelatorioCoach(
                    [
                        'nome' => $aluno->nome,
                        'periodoLabel' => $periodoLabel,
                        'relatorios' => $nomesAnexos !== [] ? $nomesAnexos : array_values($nomePorModelo),
                        'nivelDesempenho' => $nivelNome,
                        'textoDesempenho' => $textoDesempenho,
                        'metricasDesempenho' => $avaliacao['metricas'],
                    ],
                    $aluno->email,
                    $pdfs
                );
                $this->log("[{$aluno->nome}] E-mail enviado para {$aluno->email} com ".count($pdfs).' anexo(s)');
                $enviados++;
            } catch (Throwable $exc) {
                $falhas++;
                $this->log("[{$aluno->nome}] Falha ao enviar e-mail: ".$exc->getMessage());
                Log::warning('Falha ao enviar relatório do coach', [
                    'aluno_id' => $aluno->id,
                    'email' => $aluno->email,
                    'erro' => $exc->getMessage(),
                ]);
            }
        }

        $this->log(str_repeat('=', 50));
        $this->log("E-mails enviados: {$enviados} | pulados: {$pulados} | falhas: {$falhas}");
    }

    /**
     * Fallback Dompdf para o modelo progresso (quando Node/Puppeteer falha).
     */
    private function gerarPdfProgressoDoHtml(
        string $nome,
        string $id,
        string $html,
        string $dtIniIso,
        string $dtFimIso,
    ): ?string {
        $this->log("[{$nome}] Montando PDF de Progresso do plano (Dompdf)...");

        $xp = $this->loadDom($html);
        $periodo = $this->xpathText($xp, "//*[contains(@class,'report-header')]//p")
            ?: "Período do relatório: de {$dtIniIso} a {$dtFimIso}";
        $curso = $this->xpathText($xp, "//*[contains(@class,'report-aluno-desc')]//p")
            ?: '';

        $panoramaHtml = $this->montarHtmlPanoramaProgresso($xp);
        $motivacaoHtml = $this->montarHtmlMotivacaoProgresso($xp);
        $perguntasHtml = $this->montarHtmlPerguntasProgresso($xp);
        $assuntosHtml = $this->montarHtmlAssuntos($xp);

        // Mesmos gráficos do PDFWriter.start() do painel (ordem das páginas oficiais)
        $chartPrincipal = $this->chartImgHtml($html, 'chart_progresso_principal', null);
        $chartModalidades = $this->chartImgHtml($html, 'chart_progresso_modalidades', null);
        $chartTop = $this->chartImgHtml($html, 'chart_top_disciplinas', null);
        $chartPizzaMod = $this->chartImgHtml($html, 'chart_pizza_modalidades', null);
        $chartHoras = $this->chartImgHtml($html, 'chart_horas_diarias', null);
        $chartTx = $this->chartImgHtml($html, 'chart_tx_acerto', null);
        $chartBar = $this->chartImgHtml($html, 'chart_bar_questoes_disciplina', null);
        $chartPizzaQ = $this->chartImgHtml($html, 'chart_pizza_questoes', null);
        $chartLinha = $this->chartImgHtml($html, 'chart_linha_evolucao_questoes', null);
        $chartEstudo = $this->chartImgHtml($html, 'chart_progresso_estudo', null);
        $chartResumo = $this->chartImgHtml($html, 'chart_progresso_resumo', null);
        $chartRevisao = $this->chartImgHtml($html, 'chart_progresso_revisao', null);
        $chartExercicio = $this->chartImgHtml($html, 'chart_progresso_exercicio', null);

        $desc1 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-1')]/following-sibling::p[1]")
            ?: 'Confira o seu progresso geral no plano de estudos', ENT_QUOTES, 'UTF-8');
        $desc1b = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-1-1')]")
            ?: 'Confira a seguir, o progresso por modalidade de estudo:', ENT_QUOTES, 'UTF-8');
        $desc2 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-2')]/following-sibling::p[1]")
            ?: 'Confira as disciplinas com que você mais teve contato no período', ENT_QUOTES, 'UTF-8');
        $desc2b = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-2-1')]")
            ?: 'Esse é o panorama das modalidades de estudo praticadas', ENT_QUOTES, 'UTF-8');
        $desc3 = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-3-1')]")
            ?: 'Agora, vamos comparar as horas por dia com o seu horário atual', ENT_QUOTES, 'UTF-8');
        $desc4 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-4')]/following-sibling::p[1]")
            ?: 'Confira o histórico da sua taxa de acerto de questões', ENT_QUOTES, 'UTF-8');
        $desc4b = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-4-1')]")
            ?: 'Aqui estão as matérias em que você vai bem ou precisa melhorar', ENT_QUOTES, 'UTF-8');
        $desc4c = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-4-2')]")
            ?: 'Esse aqui é o panorama de todas as disciplinas que você praticou', ENT_QUOTES, 'UTF-8');
        $desc4d = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-4-3')]")
            ?: 'Agora, vamos conferir quais disciplinas estão sendo mais praticadas', ENT_QUOTES, 'UTF-8');
        $desc4e = htmlspecialchars($this->xpathText($xp, "//*[contains(@class,'section-4-4')]")
            ?: 'Por fim, vamos analisar a evolução do seu desempenho por disciplina', ENT_QUOTES, 'UTF-8');
        $desc5 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-5')]/following-sibling::p[1]")
            ?: 'Confira o seu progresso por modalidade de estudo', ENT_QUOTES, 'UTF-8');
        $desc6 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-6')]/following-sibling::p[1]")
            ?: 'Confira o seu progresso por modalidade de resumo', ENT_QUOTES, 'UTF-8');
        $desc7 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-7')]/following-sibling::p[1]")
            ?: 'Confira o seu progresso por modalidade de revisão', ENT_QUOTES, 'UTF-8');
        $desc8 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-8')]/following-sibling::p[1]")
            ?: 'Confira o seu progresso por modalidade de exercício', ENT_QUOTES, 'UTF-8');
        $desc9 = htmlspecialchars($this->xpathText($xp, "//h2[contains(@class,'section-9')]/following-sibling::p[1]")
            ?: 'Para finalizar, segue o seu histórico de metas cumpridas neste período:', ENT_QUOTES, 'UTF-8');

        $seguroNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $seguroCurso = htmlspecialchars($curso, ENT_QUOTES, 'UTF-8');
        $seguroPeriodo = htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8');
        $logoHtml = $this->montarHtmlLogoPdf();
        $logoFontFace = $this->montarCssFonteLogoPdf();

        $pdfHtml = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
{$logoFontFace}
body{font-family: DejaVu Sans, sans-serif; font-size:12px; color:#222; margin:24px;}
.logo{text-align:center; margin:0 0 12px; color:#F8C000; font-family:'FredokaBrand', DejaVu Sans, sans-serif; font-weight:bold; line-height:0.95;}
.logo .l1{font-size:26pt; letter-spacing:0.2pt;}
.logo .l2{font-size:22pt; letter-spacing:0.2pt;}
.logo .icon{width:22pt; height:22pt; vertical-align:middle; margin-left:2pt; margin-top:-4pt;}
h1{font-size:20px; margin:0 0 6px; text-align:center;}
.periodo{color:#555; margin-bottom:14px; text-align:center;}
.aluno{margin:10px 0 18px;}
.aluno h2{margin:0 0 4px; font-size:16px;}
.section{margin:22px 0 8px; padding-left:10px; border-left:4px solid #00aced; font-size:15px;}
.section-desc{color:#555; margin:0 0 12px;}
.panorama{width:100%; border-collapse:separate; border-spacing:8px 0; margin:6px 0 14px; table-layout:fixed;}
.panorama td{
  border:0.4pt solid #cdcdcd;
  padding:6pt 8pt 10pt;
  vertical-align:top;
  background:#ffffff;
}
.panorama .label{color:#888; font-size:8pt; margin:0 0 8pt; line-height:1.1;}
.panorama .value{font-size:11pt; font-weight:bold; color:#000000; margin:0; line-height:1.1;}
.chart{width:100%; max-width:700px; max-height:280px; margin:8px 0 14px;}
.chart-sm{width:100%; max-width:700px; max-height:140px; margin:8px 0 14px;}
.assuntos{width:100%; border-collapse:collapse; margin-top:8px; font-size:11px;}
.assuntos thead td{background:#00aced; color:#fff; font-weight:bold; padding:8px;}
.assuntos tbody td{border-bottom:1px solid #eee; padding:8px; vertical-align:top;}
.assuntos .taxa{text-align:right; font-weight:bold;}
.motivacao p{margin:0 0 8px; line-height:1.4;}
.questions{width:100%; border-collapse:collapse; margin:8px 0 16px;}
.questions td{width:50%; text-align:center; vertical-align:top; padding:8px;}
.rule{border:0;border-top:1px solid #eaeaea; margin:12px 0;}
</style></head><body>
{$logoHtml}
<h1>Progresso no Plano</h1>
<div class="periodo">{$seguroPeriodo}</div>
<hr class="rule" />
<div class="aluno">
  <h2>{$seguroNome}</h2>
  <div class="periodo" style="text-align:left;margin:0;">{$seguroCurso}</div>
</div>

<h2 class="section">Progresso no Plano</h2>
<p class="section-desc">{$desc1}</p>
{$chartPrincipal}
{$panoramaHtml}
<p class="section-desc">{$desc1b}</p>
{$chartModalidades}

<div style="page-break-before: always;"></div>
<h2 class="section">Panorama</h2>
<p class="section-desc">{$desc2}</p>
{$chartTop}
<p class="section-desc">{$desc2b}</p>
{$chartPizzaMod}

<div style="page-break-before: always;"></div>
<h2 class="section">Motivação</h2>
<p class="section-desc">{$desc3}</p>
{$chartHoras}
<div class="motivacao">{$motivacaoHtml}</div>

<div style="page-break-before: always;"></div>
<h2 class="section">Desempenho de Questões</h2>
<p class="section-desc">{$desc4}</p>
{$chartTx}
<p class="section-desc">{$desc4b}</p>
{$perguntasHtml}
<p class="section-desc">{$desc4c}</p>
{$chartBar}

<div style="page-break-before: always;"></div>
<p class="section-desc">{$desc4d}</p>
{$chartPizzaQ}
<p class="section-desc">{$desc4e}</p>
{$chartLinha}

<div style="page-break-before: always;"></div>
<h2 class="section">Progresso por Modalidade</h2>
<p class="section-desc">{$desc5}</p>
{$chartEstudo}

<div style="page-break-before: always;"></div>
<h2 class="section">Progresso por Modalidade</h2>
<p class="section-desc">{$desc6}</p>
{$chartResumo}

<div style="page-break-before: always;"></div>
<h2 class="section">Progresso por Modalidade</h2>
<p class="section-desc">{$desc7}</p>
{$chartRevisao}

<div style="page-break-before: always;"></div>
<h2 class="section">Progresso por Modalidade</h2>
<p class="section-desc">{$desc8}</p>
{$chartExercicio}

<div style="page-break-before: always;"></div>
<h2 class="section">Desempenho de Questões</h2>
<p class="section-desc">{$desc9}</p>
{$assuntosHtml}
</body></html>
HTML;

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $chroot = array_values(array_filter([
                base_path(),
                public_path(),
                storage_path('fonts'),
            ], 'is_dir'));
            if ($chroot !== []) {
                $options->setChroot($chroot);
            }
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdfHtml, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $this->salvarBytesPdf($nome, $dompdf->output() ?? '', 'progresso', $id);
        } catch (Throwable $exc) {
            $this->log("[{$nome}] Erro Dompdf (progresso): ".$exc->getMessage());

            return null;
        }
    }

    private function montarHtmlPanoramaProgresso(DOMXPath $xp): string
    {
        $rows = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' row-numbers ')]");
        if ($rows === false || $rows->length === 0) {
            return '<p style="color:#888;">(Panorama de progresso indisponível na página)</p>';
        }

        $cells = '';
        $primeiro = $rows->item(0);
        if (! $primeiro instanceof DOMElement) {
            return '<p style="color:#888;">(Panorama de progresso indisponível na página)</p>';
        }

        foreach ($primeiro->getElementsByTagName('div') as $col) {
            if (! $col instanceof DOMElement) {
                continue;
            }
            $class = ' '.$col->getAttribute('class').' ';
            if (! str_contains($class, ' col-')) {
                continue;
            }
            $h5 = '';
            $span = '';
            foreach ($col->getElementsByTagName('h5') as $el) {
                $h5 = trim((string) $el->textContent);
                break;
            }
            foreach ($col->getElementsByTagName('span') as $el) {
                $span = trim((string) $el->textContent);
                break;
            }
            if ($h5 === '' && $span === '') {
                continue;
            }
            $cells .= '<td><div class="label">'.htmlspecialchars($span, ENT_QUOTES, 'UTF-8').'</div>'
                .'<div class="value">'.htmlspecialchars($h5, ENT_QUOTES, 'UTF-8').'</div></td>';
        }

        if ($cells === '') {
            return '<p style="color:#888;">(Panorama de progresso vazio)</p>';
        }

        return '<table class="panorama"><tr>'.$cells.'</tr></table>';
    }

    /**
     * Extrai métricas do panorama do Progresso do plano (.row-numbers).
     *
     * @return array<string, float|null>
     */
    public function extrairMetricasProgresso(string $html): array
    {
        $xp = $this->loadDom($html);
        $avaliador = new AvaliadorDesempenho;
        $brutos = [
            'horas_brutas' => null,
            'horas_liquidas' => null,
            'dias' => null,
            'semanas' => null,
            'pct_questoes' => null,
        ];

        $mapaLabels = [
            'horasbrutas' => 'horas_brutas',
            'horasliquidas' => 'horas_liquidas',
            'dias' => 'dias',
            'semanas' => 'semanas',
            'questoes' => 'pct_questoes',
            'pctquestoes' => 'pct_questoes',
        ];

        $rows = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' row-numbers ')]");
        if ($rows !== false && $rows->length > 0) {
            $primeiro = $rows->item(0);
            if ($primeiro instanceof DOMElement) {
                foreach ($primeiro->getElementsByTagName('div') as $col) {
                    if (! $col instanceof DOMElement) {
                        continue;
                    }
                    $class = ' '.$col->getAttribute('class').' ';
                    if (! str_contains($class, ' col-')) {
                        continue;
                    }
                    $valor = '';
                    $label = '';
                    foreach ($col->getElementsByTagName('h5') as $el) {
                        $valor = trim((string) $el->textContent);
                        break;
                    }
                    foreach ($col->getElementsByTagName('span') as $el) {
                        $label = trim((string) $el->textContent);
                        break;
                    }
                    $labelKey = $this->normalizarParaComparacao($label);
                    $labelKey = str_replace('%', '', $labelKey);
                    if (isset($mapaLabels[$labelKey])) {
                        $brutos[$mapaLabels[$labelKey]] = $valor;
                    }
                }
            }
        }

        return $avaliador->normalizarMetricas($brutos);
    }

    private function salvarMetricasProgressoSeAplicavel(string $pdfPath, string $model, string $html): void
    {
        if ($model !== 'progresso' || $pdfPath === '' || ! is_file($pdfPath)) {
            return;
        }

        $metricas = $this->extrairMetricasProgresso($html);
        $sidecar = $this->caminhoMetricasSidecar($pdfPath);
        $payload = [
            'gerado_em' => date('c'),
            'periodo' => $this->periodo,
            'metricas' => $metricas,
            'metricas_raw' => $metricas,
        ];
        file_put_contents($sidecar, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log('Métricas de desempenho salvas: '.$sidecar);
    }

    private function caminhoMetricasSidecar(string $pdfPath): string
    {
        if (str_ends_with(strtolower($pdfPath), '.pdf')) {
            return substr($pdfPath, 0, -4).'.metricas.json';
        }

        return $pdfPath.'.metricas.json';
    }

    /**
     * @param  list<string>  $pdfs
     * @return array{nivel: \App\Models\NivelDesempenho|null, metricas: array<string, float|null>, motivo: string}
     */
    private function avaliarDesempenhoDoProgresso(array $pdfs): array
    {
        $avaliador = new AvaliadorDesempenho;
        $metricas = null;

        foreach ($pdfs as $pdf) {
            $meta = $this->extrairMetaDoArquivoPdf(basename($pdf));
            if ($meta === null || ($meta['model'] ?? '') !== 'progresso') {
                continue;
            }
            $sidecar = $this->caminhoMetricasSidecar($pdf);
            if (! is_file($sidecar)) {
                continue;
            }
            $json = json_decode((string) file_get_contents($sidecar), true);
            if (! is_array($json)) {
                continue;
            }
            $metricas = is_array($json['metricas'] ?? null) ? $json['metricas'] : null;
            break;
        }

        if ($metricas === null) {
            return [
                'nivel' => null,
                'metricas' => $avaliador->normalizarMetricas([]),
                'motivo' => 'Sem métricas do Progresso do plano (arquivo .metricas.json ausente).',
            ];
        }

        return $avaliador->avaliar($metricas);
    }

    private function montarHtmlMotivacaoProgresso(DOMXPath $xp): string
    {
        $nodes = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' insights-panel ')]//p");
        if ($nodes === false || $nodes->length === 0) {
            // fallback: parágrafo após h2 Motivação
            $nodes = $xp->query("//h2[contains(.,'Motiv')]/following-sibling::p[position()<=6]");
        }
        if ($nodes === false || $nodes->length === 0) {
            return '<p style="color:#888;">(Seção Motivação indisponível na página)</p>';
        }

        $titulo = $this->xpathText($xp, "//*[contains(concat(' ', normalize-space(@class), ' '), ' insights-panel ')]//h6");
        $out = $titulo !== ''
            ? '<p><b>'.htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8').'</b></p>'
            : '<p><b>Painel de Insights</b></p>';
        foreach ($nodes as $node) {
            $txt = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
            if ($txt === '') {
                continue;
            }
            $out .= '<p>'.htmlspecialchars($txt, ENT_QUOTES, 'UTF-8').'</p>';
        }

        return $out !== '' ? $out : '<p style="color:#888;">(Seção Motivação vazia)</p>';
    }

    /**
     * Melhor/pior disciplina (bloco row-questions do relatório de progresso).
     */
    private function montarHtmlPerguntasProgresso(DOMXPath $xp): string
    {
        $cols = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' row-questions ')]//*[contains(@class,'col-')]");
        if ($cols === false || $cols->length === 0) {
            return '';
        }

        $cells = '';
        foreach ($cols as $col) {
            if (! $col instanceof DOMElement) {
                continue;
            }
            $txt = trim(preg_replace('/\s+/', ' ', (string) $col->textContent) ?? '');
            if ($txt === '') {
                continue;
            }
            $cells .= '<td>'.nl2br(htmlspecialchars($txt, ENT_QUOTES, 'UTF-8')).'</td>';
        }

        return $cells !== '' ? '<table class="questions"><tr>'.$cells.'</tr></table>' : '';
    }

    private function gerarPdfDoHtml(
        string $nome,
        string $id,
        string $html,
        string $dtIniIso,
        string $dtFimIso,
        string $model = 'questoes',
    ): ?string {
        $this->log("[{$nome}] Montando PDF (Dompdf: panorama + gráficos + assuntos)...");

        $xp = $this->loadDom($html);
        $periodo = $this->xpathText($xp, "//*[contains(@class,'report-header')]//p")
            ?: "Período do relatório: de {$dtIniIso} a {$dtFimIso}";
        $curso = $this->xpathText($xp, "//*[contains(@class,'report-aluno-desc')]//p")
            ?: '';

        $panoramaHtml = $this->montarHtmlPanorama($xp);
        $assuntosHtml = $this->montarHtmlAssuntos($xp);

        $chartDia = $this->chartImgHtml($html, 'chart_questoes_dia', 'Acertos e Erros por Dia');
        $chartBolha = $this->chartImgHtml($html, 'chart_bolha_questoes', null);
        $chartMelhores = $this->chartImgHtml($html, 'chart_top_melhores', null);
        $chartPiores = $this->chartImgHtml($html, 'chart_top_piores', null);
        $chartEvolucao = $this->chartImgHtml($html, 'chart_evolucao_materia', null);

        $sec2Desc = $this->xpathText($xp, "//h2[contains(@class,'section-2')]/following-sibling::p[1]")
            ?: 'Confira o seu desempenho por disciplinas';
        $sec3Desc = $this->xpathText($xp, "//h2[contains(@class,'section-3')]/following-sibling::p[1]")
            ?: 'Agora, vamos acompanhar sua evolução no tempo por matéria';
        $melhoresTitulo = $this->xpathText($xp, "//*[contains(@class,'section-2-1')]") ?: 'Suas 3 melhores matérias';
        $pioresTitulo = $this->xpathText($xp, "//*[contains(@class,'section-2-2')]") ?: 'Suas 3 piores matérias';

        $seguroNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $seguroCurso = htmlspecialchars($curso, ENT_QUOTES, 'UTF-8');
        $seguroPeriodo = htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8');
        $seguroSec2 = htmlspecialchars($sec2Desc, ENT_QUOTES, 'UTF-8');
        $seguroSec3 = htmlspecialchars($sec3Desc, ENT_QUOTES, 'UTF-8');
        $seguroMelhores = htmlspecialchars($melhoresTitulo, ENT_QUOTES, 'UTF-8');
        $seguroPiores = htmlspecialchars($pioresTitulo, ENT_QUOTES, 'UTF-8');
        $logoHtml = $this->montarHtmlLogoPdf();

        $logoFontFace = $this->montarCssFonteLogoPdf();

        $pdfHtml = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
{$logoFontFace}
body{font-family: DejaVu Sans, sans-serif; font-size:12px; color:#222; margin:24px;}
.logo{text-align:center; margin:0 0 12px; color:#F8C000; font-family:'FredokaBrand', DejaVu Sans, sans-serif; font-weight:bold; line-height:0.95;}
.logo .l1{font-size:26pt; letter-spacing:0.2pt;}
.logo .l2{font-size:22pt; letter-spacing:0.2pt;}
.logo .icon{width:22pt; height:22pt; vertical-align:middle; margin-left:2pt; margin-top:-4pt;}
h1{font-size:20px; margin:0 0 6px; text-align:center;}
.periodo{color:#555; margin-bottom:14px; text-align:center;}
.aluno{margin:10px 0 18px;}
.aluno h2{margin:0 0 4px; font-size:16px;}
.section{margin:22px 0 8px; padding-left:10px; border-left:4px solid #00aced; font-size:15px;}
.section-desc{color:#555; margin:0 0 12px;}
.panorama{width:100%; border-collapse:separate; border-spacing:10px 0; margin:6px 0 14px; table-layout:fixed;}
.panorama td{
  width:33%;
  border:0.4pt solid #cdcdcd;
  padding:6pt 8pt 10pt;
  height:52pt;
  vertical-align:top;
  background:#ffffff;
}
.panorama .label{color:#cccccc; font-size:9pt; margin:0 0 12pt; line-height:1.1;}
.panorama .value{font-size:12pt; font-weight:bold; color:#000000; margin:0; line-height:1.1;}
.chart{width:100%; max-width:700px; max-height:280px; margin:8px 0 14px;}
.chart-sm{width:100%; max-width:700px; max-height:140px; margin:8px 0 14px;}
.two-col{width:100%; border-collapse:collapse;}
.two-col td{width:50%; vertical-align:top; padding:4px;}
.assuntos{width:100%; border-collapse:collapse; margin-top:8px; font-size:11px;}
.assuntos thead td{background:#00aced; color:#fff; font-weight:bold; padding:8px;}
.assuntos tbody td{border-bottom:1px solid #eee; padding:8px; vertical-align:top;}
.assuntos .taxa{text-align:right; font-weight:bold;}
.rule{border:0;border-top:1px solid #eaeaea; margin:12px 0;}
</style></head><body>
{$logoHtml}
<h1>Relatório de Questões</h1>
<div class="periodo">{$seguroPeriodo}</div>
<hr class="rule" />
<div class="aluno">
  <h2>{$seguroNome}</h2>
  <div class="periodo" style="text-align:left;margin:0;">{$seguroCurso}</div>
</div>

<h2 class="section">Breve Panorama</h2>
<p class="section-desc">Confira o seu desempenho de questões no período</p>
{$panoramaHtml}
{$chartDia}

<div style="page-break-before: always;"></div>
<h2 class="section">Desempenho por Disciplina</h2>
<p class="section-desc">{$seguroSec2}</p>
{$chartBolha}
<table class="two-col"><tr>
  <td><p><b>{$seguroMelhores}</b></p>{$chartMelhores}</td>
  <td><p><b>{$seguroPiores}</b></p>{$chartPiores}</td>
</tr></table>

<div style="page-break-before: always;"></div>
<h2 class="section">Evolução do Desempenho por Disciplina</h2>
<p class="section-desc">{$seguroSec3}</p>
{$chartEvolucao}

<div style="page-break-before: always;"></div>
<h2 class="section">Performance por assunto</h2>
<p class="section-desc">Confira o desempenho de questões por assunto no período do relatório:</p>
{$assuntosHtml}
</body></html>
HTML;

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $chroot = array_values(array_filter([
                base_path(),
                public_path(),
                storage_path('fonts'),
            ], 'is_dir'));
            if ($chroot !== []) {
                $options->setChroot($chroot);
            }
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdfHtml, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $this->salvarBytesPdf($nome, $dompdf->output() ?? '', $model, $id);
        } catch (Throwable $exc) {
            $this->log("[{$nome}] Erro Dompdf: " . $exc->getMessage());

            return null;
        }
    }

    /**
     * Logo vetorial no topo do PDF (texto + ícone), nítida em qualquer zoom.
     */
    private function montarHtmlLogoPdf(): string
    {
        $iconSrc = $this->logoIconDataUri();
        $iconHtml = $iconSrc !== ''
            ? '<img class="icon" src="' . $iconSrc . '" alt="" />'
            : '';

        return '<div class="logo">'
            . '<div class="l1">Missão' . $iconHtml . '</div>'
            . '<div class="l2">nomeação</div>'
            . '</div>';
    }

    private function montarCssFonteLogoPdf(): string
    {
        $path = storage_path('fonts/Fredoka-Bold.ttf');
        if (! is_file($path)) {
            return '';
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return '';
        }

        $src = 'data:font/truetype;base64,' . base64_encode($bytes);

        return "@font-face{font-family:'FredokaBrand';font-style:normal;font-weight:bold;src:url('{$src}') format('truetype');}";
    }

    private function logoIconDataUri(): string
    {
        $path = public_path('img/logo-missao-icon.png');
        if (! is_file($path)) {
            return '';
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    private function montarHtmlPanorama(DOMXPath $xp): string
    {
        $nodes = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' main-numbers ')]");
        if ($nodes === false || $nodes->length === 0) {
            return '<p style="color:#888;">(Breve Panorama indisponível na página)</p>';
        }

        $cells = '';
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $label = '';
            $value = '';
            foreach ($node->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($child->tagName);
                if ($tag === 'p') {
                    $label = trim((string) $child->textContent);
                }
                if ($tag === 'h3') {
                    $value = trim((string) $child->textContent);
                }
            }
            if ($label === '' && $value === '') {
                continue;
            }
            $cells .= '<td><div class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div></td>';
        }

        if ($cells === '') {
            return '<p style="color:#888;">(Breve Panorama vazio)</p>';
        }

        return '<table class="panorama"><tr>' . $cells . '</tr></table>';
    }

    private function montarHtmlAssuntos(DOMXPath $xp): string
    {
        $rows = $xp->query("//*[@id='tabela_questoes']//tbody/tr");
        if ($rows === false || $rows->length === 0) {
            return '<p style="color:#888;">(Performance por assunto indisponível na página)</p>';
        }

        $body = '';
        foreach ($rows as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }
            $tds = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $tds[] = $td;
            }
            if (count($tds) < 3) {
                continue;
            }
            $disciplina = htmlspecialchars(trim((string) $tds[0]->textContent), ENT_QUOTES, 'UTF-8');
            $assunto = htmlspecialchars(trim((string) $tds[1]->textContent), ENT_QUOTES, 'UTF-8');
            $taxa = htmlspecialchars(trim((string) $tds[2]->textContent), ENT_QUOTES, 'UTF-8');
            $style = trim($tds[2]->getAttribute('style'));
            $color = '#d09611';
            if (preg_match('/color\s*:\s*([^;]+)/i', $style, $m)) {
                $color = trim($m[1]);
            }
            $body .= '<tr>'
                . '<td>' . $disciplina . '</td>'
                . '<td>' . $assunto . '</td>'
                . '<td class="taxa" style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';">' . $taxa . '</td>'
                . '</tr>';
        }

        return '<table class="assuntos"><thead><tr>'
            . '<td>Disciplina</td><td>Assunto</td><td>Taxa de Acertos</td>'
            . '</tr></thead><tbody>' . $body . '</tbody></table>';
    }

    private function chartImgHtml(string $html, string $chartId, ?string $titulo): string
    {
        if (! extension_loaded('gd')) {
            return $titulo
                ? '<p style="color:#888;">(' . $titulo . ' omitido: instale php-gd)</p>'
                : '';
        }
        $cfg = $this->extrairChartConfig($html, $chartId);
        if ($cfg === null) {
            return '';
        }
        $img = $this->quickChartPngDataUri($cfg);
        if ($img === null) {
            return '';
        }
        $out = '';
        if ($titulo !== null && $titulo !== '') {
            $out .= '<p style="text-align:center;font-weight:bold;margin:8px 0;">'
                . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $out .= '<img class="' . (
            in_array($chartId, ['chart_progresso_principal'], true) ? 'chart-sm' : 'chart'
        ) . '" src="' . $img . '" />';

        return $out;
    }

    private function xpathText(DOMXPath $xp, string $query): string
    {
        $nodes = $xp->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', (string) $nodes->item(0)?->textContent) ?? '');
    }

    /**
     * Extrai o objeto passado a `new Chart(el, { ... })` para o canvas informado.
     *
     * @return array<string, mixed>|null
     */
    private function extrairChartConfig(string $html, string $canvasId): ?array
    {
        // var elFoo = document.getElementById('chart_...');
        // new Chart(elFoo, { ... });
        $elVar = null;
        if (preg_match(
            '/var\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*document\.getElementById\(\s*[\'"]' . preg_quote($canvasId, '/') . '[\'"]\s*\)/',
            $html,
            $vm
        )) {
            $elVar = $vm[1];
        }

        $jsonish = null;
        if ($elVar !== null) {
            $needle = 'new Chart(' . $elVar;
            $pos = strpos($html, $needle);
            if ($pos === false) {
                $pos = strpos($html, 'new Chart( ' . $elVar);
            }
            if ($pos !== false) {
                $slice = substr($html, $pos, 20000);
                if (preg_match('/new\s+Chart\s*\(\s*' . preg_quote($elVar, '/') . '\s*,\s*(\{)/', $slice, $m, PREG_OFFSET_CAPTURE)) {
                    $jsonish = $this->extrairObjetoJsBalanceado($slice, (int) $m[1][1]);
                }
            }
        }

        // Fallback: new Chart(document.getElementById('id'), { ... })
        if ($jsonish === null) {
            if (preg_match(
                '/new\s+Chart\s*\(\s*document\.getElementById\(\s*[\'"]' . preg_quote($canvasId, '/') . '[\'"]\s*\)\s*,\s*(\{)/',
                $html,
                $m,
                PREG_OFFSET_CAPTURE
            )) {
                $jsonish = $this->extrairObjetoJsBalanceado($html, (int) $m[1][1]);
            }
        }

        if ($jsonish === null) {
            return null;
        }

        $colors = $this->extrairChartColors($html);
        if ($colors === []) {
            $colors = ['#00ACED', '#FF595E', '#FFCA3A', '#8AC926', '#6A4C93'];
        }

        // JS object → JSON aproximado
        $json = $jsonish;
        // Substitui chartColors[Math.floor(Math.random()*chartColors.length)] por cores fixas
        $colorIdx = 0;
        $json = preg_replace_callback(
            '/chartColors\s*\[\s*Math\.floor\s*\(\s*Math\.random\s*\(\s*\)\s*\*\s*chartColors\.length\s*\)\s*\]/',
            static function () use (&$colorIdx, $colors): string {
                $c = $colors[$colorIdx % count($colors)];
                $colorIdx++;

                return "'" . $c . "'";
            },
            $json
        ) ?? $json;

        // Remove functions antes de expandir chartColors (evita lixo tipo chartColors[context.dataIndex])
        $json = preg_replace('/formatter\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/label\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/backgroundColor\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/[A-Za-z_][A-Za-z0-9_]*\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/function\s*\(.*?\)\s*\{.*?\},?/s', 'null,', $json) ?? $json;

        // Expandir chartColors[n] / chartColors[expr] restantes para cor fixa
        $json = preg_replace_callback(
            '/chartColors\s*\[\s*([^\]]+)\s*\]/',
            static function (array $m) use (&$colorIdx, $colors): string {
                $inner = trim($m[1]);
                if (ctype_digit($inner)) {
                    $c = $colors[((int) $inner) % count($colors)];
                } else {
                    $c = $colors[$colorIdx % count($colors)];
                    $colorIdx++;
                }

                return "'" . $c . "'";
            },
            $json
        ) ?? $json;

        // Expandir chartColors sem índice (ex.: backgroundColor: chartColors)
        $coresJson = json_encode(array_values($colors), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($coresJson === false) {
            $coresJson = '["#00ACED","#FF595E","#FFCA3A","#8AC926","#6A4C93"]';
        }
        $json = preg_replace('/\bchartColors\b/', $coresJson, $json) ?? $json;

        $json = preg_replace('/([{\[,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $json) ?? $json;
        $json = str_replace("'", '"', $json);
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        // remove functions residuais (já com aspas após quoting)
        $json = preg_replace('/"formatter"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/"label"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/"function"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/function\s*\(.*?\)\s*\{.*?\},?/s', 'null,', $json) ?? $json;
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;

        $data = json_decode($json, true);
        if (! is_array($data)) {
            $this->log("Chart {$canvasId}: JSON inválido após normalização (".json_last_error_msg().')');

            return null;
        }

        $data = $this->normalizarCoresDataset($data);

        return $this->aplicarDatalabelsOficiais($data, $canvasId);
    }

    /**
     * Limita backgroundColor ao nº de pontos (evita barra única com array enorme de cores).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCoresDataset(array $data): array
    {
        $datasets = $data['data']['datasets'] ?? null;
        if (! is_array($datasets)) {
            return $data;
        }

        foreach ($datasets as $i => $ds) {
            if (! is_array($ds)) {
                continue;
            }
            $points = is_array($ds['data'] ?? null) ? count($ds['data']) : 0;
            $bg = $ds['backgroundColor'] ?? null;
            if ($points > 0 && is_array($bg) && count($bg) > $points) {
                $data['data']['datasets'][$i]['backgroundColor'] = array_values(array_slice($bg, 0, max($points, 1)));
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function extrairChartColors(string $html): array
    {
        $defaults = [
            '#00ACED',
            '#FF595E',
            '#FFCA3A',
            '#8AC926',
            '#6A4C93',
            '#FF70A6',
            '#6D4C3D',
            '#0D0221',
            '#5CF64A',
            '#F94144',
            '#F3722C',
            '#F9C74F',
            '#90BE6D',
            '#43AA8B',
            '#577590',
        ];
        if (! preg_match('/var\s+chartColors\s*=\s*\[(.*?)\]/s', $html, $m)) {
            return $defaults;
        }
        preg_match_all('/[\'"](#[0-9A-Fa-f]{3,8})[\'"]/', $m[1], $cm);
        $colors = array_values(array_unique($cm[1] ?? []));

        return $colors !== [] ? $colors : $defaults;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function aplicarDatalabelsOficiais(array $data, string $canvasId): array
    {
        $type = (string) ($data['type'] ?? '');

        // Garante legenda com nomes das disciplinas (bolha / evolução)
        if (in_array($canvasId, ['chart_bolha_questoes', 'chart_evolucao_materia'], true)) {
            $data['options']['legend']['display'] = true;
            $data['options']['legend']['labels'] = [
                'boxWidth' => 12,
                'fontSize' => 9,
            ];
        }

        // Labels aninhados do Chart.js v2 → texto único (QuickChart renderiza melhor)
        if (isset($data['data']['labels']) && is_array($data['data']['labels'])) {
            $data['data']['labels'] = array_map(static function ($label) {
                if (is_array($label)) {
                    return trim(implode(' ', array_map(static fn ($p) => (string) $p, $label)));
                }

                return $label;
            }, $data['data']['labels']);
        }

        // Barras: nomes das disciplinas no eixo X
        if ($type === 'bar' || $type === 'horizontalBar') {
            if ($type === 'bar') {
                $data['options']['scales']['xAxes'] = [[
                    'ticks' => [
                        'fontSize' => 8,
                        'autoSkip' => false,
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                ]];
                if (! isset($data['options']['scales']['yAxes'])) {
                    $data['options']['scales']['yAxes'] = [[
                        'ticks' => ['beginAtZero' => true, 'max' => 120],
                    ]];
                }
            }
            // horizontalBar: preserva scales do HTML (já vêm prontas do Tutory)
            $data['options']['legend']['display'] = false;
            $suffix = match ($canvasId) {
                'chart_top_disciplinas' => '__DATALABEL_HOURS__',
                'chart_pizza_modalidades' => '__DATALABEL_HOURS__',
                default => '__DATALABEL_PERCENT__',
            };
            $data['options']['plugins']['datalabels'] = [
                'color' => '#333',
                'anchor' => 'end',
                'align' => 'end',
                'offset' => 6,
                'font' => ['size' => 10],
                'formatter' => $suffix,
            ];

            return $data;
        }

        if ($type === 'pie' || $type === 'doughnut') {
            $data['options']['legend']['display'] = true;
            $data['options']['plugins']['datalabels'] = [
                'color' => '#fff',
                'backgroundColor' => '#000',
                'anchor' => 'end',
                'align' => 'end',
                'offset' => -16,
                'font' => ['size' => 10],
                'formatter' => in_array($canvasId, ['chart_pizza_modalidades'], true)
                    ? '__DATALABEL_HOURS__'
                    : '__DATALABEL_QUESTOES__',
            ];

            return $data;
        }

        if ($type === 'bubble') {
            $data['options']['plugins']['datalabels'] = [
                'anchor' => 'end',
                'align' => 'end',
                'offset' => 8,
                'color' => '#fff',
                'backgroundColor' => '#000',
                'borderRadius' => 0,
                'padding' => 4,
                'font' => ['size' => 10],
                'formatter' => '__DATALABEL_BUBBLE_PERCENT__',
            ];
            $padding = is_array($data['options']['layout']['padding'] ?? null)
                ? $data['options']['layout']['padding']
                : [];
            $data['options']['layout']['padding'] = array_merge($padding, [
                'top' => 20,
                'right' => 16,
            ]);

            return $data;
        }

        if ($type === 'line') {
            $isQuestoesDia = $canvasId === 'chart_questoes_dia';
            $data['options']['plugins']['datalabels'] = [
                'anchor' => 'end',
                'align' => 'end',
                'offset' => 8,
                'color' => '#fff',
                'backgroundColor' => '#000',
                'borderRadius' => 0,
                'padding' => 4,
                'font' => ['size' => 10],
                'formatter' => $isQuestoesDia ? '__DATALABEL_QUESTOES__' : '__DATALABEL_PERCENT__',
            ];
            $padding = is_array($data['options']['layout']['padding'] ?? null)
                ? $data['options']['layout']['padding']
                : [];
            $data['options']['layout']['padding'] = array_merge($padding, [
                'top' => 28,
                'right' => 16,
            ]);
            if ($canvasId === 'chart_evolucao_materia') {
                $data['options']['legend']['display'] = true;
            }

            return $data;
        }

        unset($data['options']['plugins']['datalabels']);

        return $data;
    }

    private function extrairObjetoJsBalanceado(string $source, int $start): ?string
    {
        if (! isset($source[$start]) || $source[$start] !== '{') {
            return null;
        }
        $depth = 0;
        $inStr = null;
        $escape = false;
        $len = strlen($source);
        for ($i = $start; $i < $len; $i++) {
            $ch = $source[$i];
            if ($inStr !== null) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $inStr) {
                    $inStr = null;
                }

                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = $ch;

                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     */
    private function quickChartPngDataUri(array $chartConfig): ?string
    {
        try {
            // chart como string JS para permitir formatter() (caixinhas pretas "N questões" / "N%")
            $chartJs = json_encode($chartConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($chartJs === false) {
                return null;
            }
            $replacements = [
                '"__DATALABEL_QUESTOES__"' => 'function(value){return Number(value).toFixed(0)+" questões";}',
                '"__DATALABEL_PERCENT__"' => 'function(value){return Number(value).toFixed(0)+"%";}',
                '"__DATALABEL_HOURS__"' => 'function(value){return Number(value).toFixed(0)+"h";}',
                '"__DATALABEL_BUBBLE_PERCENT__"' => 'function(value){return value&&value.r!=null?Number(value.r*10).toFixed(0)+"%":"";}',
                // legado
                '"__DATALABEL_FORMATTER__"' => 'function(value){return Number(value).toFixed(0)+" questões";}',
            ];
            $chartJs = str_replace(array_keys($replacements), array_values($replacements), $chartJs);

            $type = (string) ($chartConfig['type'] ?? '');
            $labelCount = is_array($chartConfig['data']['labels'] ?? null)
                ? count($chartConfig['data']['labels'])
                : 1;
            $width = 900;
            $height = 420;
            if ($type === 'horizontalBar') {
                $height = max(110, min(420, 48 + ($labelCount * 38)));
            } elseif ($type === 'pie' || $type === 'doughnut') {
                $width = 560;
                $height = 400;
            } elseif ($type === 'bar' && $labelCount <= 4) {
                $height = 320;
            }

            $payload = [
                'width' => $width,
                'height' => $height,
                'devicePixelRatio' => 2,
                'format' => 'png',
                'backgroundColor' => 'white',
                'version' => '2.9.4',
                'plugins' => ['datalabels'],
                'chart' => $chartJs,
            ];

            $resp = Http::timeout(45)
                ->asJson()
                ->post('https://quickchart.io/chart', $payload);

            if ($resp->successful() && strlen($resp->body()) > 100) {
                $body = $resp->body();
                $ctype = (string) ($resp->header('Content-Type') ?? '');
                if (str_starts_with($body, "\x89PNG") || str_contains($ctype, 'image')) {
                    return 'data:image/png;base64,' . base64_encode($body);
                }
                $this->log('QuickChart resposta inesperada: ' . substr($body, 0, 180));
            }

            // GET fallback (sem function — só útil se não houver formatter)
            if (! str_contains($chartJs, 'function(value)')) {
                $url = 'https://quickchart.io/chart?c=' . rawurlencode($chartJs) . '&w=900&h=420&bkg=white&f=png&v=2.9.4';
                $get = Http::timeout(45)->get($url);
                if ($get->successful() && strlen($get->body()) > 100) {
                    return 'data:image/png;base64,' . base64_encode($get->body());
                }
            }
        } catch (Throwable $exc) {
            $this->log('QuickChart erro: ' . $exc->getMessage());
        }

        return null;
    }

    private function salvarBytesPdf(string $nomeAluno, string $bytes, string $model = 'questoes', ?string $id = null): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $destino = $this->caminhoDestinoPdf($nomeAluno, $model, $id);
        file_put_contents($destino, $bytes);
        $this->log("[{$nomeAluno}] Arquivo salvo: {$destino}");

        return $destino;
    }

    private function formatarDuracao(float $segundos): string
    {
        $total = (int) round($segundos);
        $h = intdiv($total, 3600);
        $m = intdiv($total % 3600, 60);
        $s = $total % 60;
        if ($h > 0) {
            return "{$h}h {$m}min {$s}s";
        }
        if ($m > 0) {
            return "{$m}min {$s}s";
        }

        return "{$s}s";
    }

    /**
     * @param  list<string>  $pdfs
     * @param  list<string>  $falhas
     * @param  list<array{nome: string, sucesso: bool, tentativas: int}>  $resultados
     */
    private function gravarLogResumo(
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        int $total,
        array $pdfs,
        array $falhas,
        array $resultados,
    ): string {
        $caminho = $this->pastaDownload . '/log_download_' . $inicio->format('Ymd_His') . '.txt';
        $linhas = [
            'Relatórios Tutory - CLI/HTTP',
            'Início: ' . $inicio->format('d/m/Y H:i:s'),
            'Fim:    ' . $fim->format('d/m/Y H:i:s'),
            'Duração: ' . $this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()),
            'Alunos: ' . $total,
            'Modelos: ' . implode(', ', array_column($this->relatorios(), 'model')),
            'PDFs: ' . count($pdfs),
            'Falhas: ' . count($falhas),
            "Pasta: {$this->pastaDownload}",
            '',
            'Arquivos:',
        ];
        $linhas = array_merge($linhas, $pdfs !== [] ? array_map(static fn($p) => '- ' . basename($p), $pdfs) : ['- (nenhum)']);
        $linhas[] = '';
        $linhas[] = 'Status:';
        foreach ($resultados as $r) {
            $linhas[] = '- [' . ($r['sucesso'] ? 'OK' : 'FALHA') . "] {$r['nome']} (tentativas: {$r['tentativas']})";
        }
        if ($falhas !== []) {
            $linhas[] = '';
            $linhas[] = 'Falhas finais:';
            foreach ($falhas as $f) {
                $linhas[] = "- {$f}";
            }
        }
        file_put_contents($caminho, implode("\n", $linhas) . "\n");

        return $caminho;
    }

    private function baixarTodos(): void
    {
        $inicio = new \DateTimeImmutable('now');
        $relatorios = $this->relatorios();
        $this->log('Processo iniciado em: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Modo: CLI/HTTP → cadastrar-relatorio-coach + Puppeteer/PDFWriter (fallback Dompdf em questões)');
        $this->log('Relatórios: ' . implode(', ', array_map(
            static fn (array $r): string => $r['nome'] . ' (' . $r['model'] . ')',
            $relatorios
        )));

        $alunos = $this->coletarAlunosAtivos();
        if ($this->teste) {
            $alvo = mb_strtolower(self::ALUNA_TESTE);
            $alunos = array_values(array_filter(
                $alunos,
                static fn(array $a) => str_contains(mb_strtolower($a['nome']), $alvo)
            ));
            if ($alunos !== []) {
                $this->log('Modo --teste: ' . $alunos[0]['nome'] . ' (id ' . $alunos[0]['id'] . ')');
            } else {
                $this->log("Modo --teste: '" . self::ALUNA_TESTE . "' não encontrada.");
            }
        }

        if ($alunos === []) {
            $fim = new \DateTimeImmutable('now');
            $log = $this->gravarLogResumo($inicio, $fim, 0, [], [], []);
            $this->log("Nenhum aluno. Log: {$log}");

            return;
        }

        /** @var array<string, array{nome: string, sucesso: bool, arquivo: ?string, tentativas: int, meta: array{nome: string, id: string}, relatorio: array{model: string, nome: string, slug: string}}> $resultados */
        $resultados = [];
        foreach ($alunos as $a) {
            foreach ($relatorios as $relatorio) {
                $chave = $a['nome'] . '|' . $relatorio['model'];
                $resultados[$chave] = [
                    'nome' => $a['nome'] . ' / ' . $relatorio['nome'],
                    'sucesso' => false,
                    'arquivo' => null,
                    'tentativas' => 0,
                    'meta' => $a,
                    'relatorio' => $relatorio,
                ];
            }
        }
        $chaves = array_keys($resultados);

        $processarLote = function (array $lista, int $rodada) use (&$resultados): void {
            $total = count($lista);
            foreach ($lista as $i => $chave) {
                $resultados[$chave]['tentativas']++;
                $t = $resultados[$chave]['tentativas'];
                $rotulo = $resultados[$chave]['nome'];
                $this->log(str_repeat('=', 50));
                $this->log("[rodada {$rodada}] Item " . ($i + 1) . "/{$total}: {$rotulo} (tentativa {$t}/" . self::MAX_TENTATIVAS . ')');
                try {
                    $arquivo = $this->processarAluno(
                        $resultados[$chave]['meta'],
                        $resultados[$chave]['relatorio']
                    );
                } catch (Throwable $exc) {
                    $this->log("[{$rotulo}] ERRO: " . $exc->getMessage());
                    $arquivo = null;
                }
                if ($arquivo !== null) {
                    $resultados[$chave]['sucesso'] = true;
                    $resultados[$chave]['arquivo'] = $arquivo;
                    $this->log("[{$rotulo}] SUCESSO");
                } else {
                    $resultados[$chave]['sucesso'] = false;
                    $resultados[$chave]['arquivo'] = null;
                    $this->log("[{$rotulo}] FALHA");
                }
            }
        };

        $processarLote($chaves, 1);
        for ($rodada = 2; $rodada <= self::MAX_TENTATIVAS; $rodada++) {
            $pendentes = array_values(array_filter($chaves, static fn($k) => ! $resultados[$k]['sucesso']));
            if ($pendentes === []) {
                $this->log(str_repeat('=', 50));
                $this->log('Nenhuma falha restante.');
                break;
            }
            $this->log(str_repeat('=', 50));
            $this->log('Reprocessando ' . count($pendentes) . " falha(s) (rodada {$rodada}/" . self::MAX_TENTATIVAS . ')...');
            $processarLote($pendentes, $rodada);
        }

        $pdfs = [];
        $falhas = [];
        foreach ($resultados as $r) {
            if ($r['sucesso'] && $r['arquivo']) {
                $pdfs[] = $r['arquivo'];
            }
            if (! $r['sucesso']) {
                $falhas[] = $r['nome'];
            }
        }
        $lista = array_map(static fn($k) => $resultados[$k], $chaves);
        $fim = new \DateTimeImmutable('now');
        $log = $this->gravarLogResumo($inicio, $fim, count($alunos), $pdfs, $falhas, $lista);

        $this->log(str_repeat('=', 50));
        $this->log('Início: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Fim:    ' . $fim->format('d/m/Y H:i:s'));
        $this->log('Duração: ' . $this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()));
        $this->log('PDFs baixados: ' . count($pdfs));
        $this->log('Falhas finais: ' . count($falhas));
        foreach ($chaves as $chave) {
            if ($resultados[$chave]['sucesso']) {
                continue;
            }
            $this->log("- {$resultados[$chave]['nome']} (tentativas: {$resultados[$chave]['tentativas']})");
        }
        $this->log("Arquivos em: {$this->pastaDownload}");
        $this->log("Log salvo em: {$log}");
    }
}
