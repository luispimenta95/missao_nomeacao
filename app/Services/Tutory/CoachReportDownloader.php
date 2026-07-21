<?php

/**
 * Baixa o Relatório do Coach no Tutory e envia por e-mail aos alunos cadastrados.
 *
 * 1. Login (/intent/login) → sessão + Bearer
 * 2. /alunos/consulta?status=ativos (cards com data-id)
 * 3. POST /intent/cadastrar-relatorio-coach (agrupamento=dia)
 * 4. GET /documentos/relatorios/questoes?key=...
 * 5. PDF oficial via Puppeteer + PDFWriter/jsPDF do painel (fallback: Dompdf)
 * 6. Reprocessa falhas (até 3x)
 * 7. Lista alunos do banco → localiza PDF em public/pdfs → e-mail se recebe_email
 */

namespace App\Services\Tutory;

use App\Http\Util\MailHelper;
use App\Models\Aluno;
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
     * @param  array{nome: string, id: string}  $aluno
     */
    private function processarAluno(array $aluno): ?string
    {
        $nome = $aluno['nome'];
        $id = $aluno['id'];
        [$dtIniIso, $dtFimIso] = $this->datasPeriodoIso();
        [$dtIni, $dtFim] = $this->datasPeriodoBr();
        $this->log("[{$nome}] id={$id} | Datas: {$dtIniIso} → {$dtFimIso} ({$dtIni} → {$dtFim})");

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

        $this->log("[{$nome}] Gerando via {$endpoint}...");
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
            $this->log("[{$nome}] Falha ao gerar: " . $erro);

            return null;
        }

        $lista = $json['data'] ?? null;
        if (! is_array($lista) || $lista === [] || empty($lista[0]['token'])) {
            $this->log("[{$nome}] Resposta sem token de relatório: " . $resp->body());

            return null;
        }

        $key = (string) $lista[0]['token'];
        $model = trim((string) env('TUTORY_REPORT_MODEL', 'questoes')) ?: 'questoes';
        $reportUrl = self::BASE . '/documentos/relatorios/' . $model . '?key=' . rawurlencode($key);
        $this->log("[{$nome}] Abrindo relatório...");

        $destino = $this->caminhoDestinoPdf($nome, $id);

        $pagina = $this->client()
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get('/documentos/relatorios/' . $model, ['key' => $key]);

        if ($pagina->status() >= 400 || ! str_contains($pagina->body(), 'btn_save')) {
            $this->log("[{$nome}] Página do relatório inválida (HTTP {$pagina->status()})");

            return null;
        }

        $html = $pagina->body();
        if (! str_contains($html, 'main-numbers') || ! str_contains($html, 'tabela_questoes')) {
            $this->log("[{$nome}] AVISO: página sem Breve Panorama ou Performance por assunto");
        }

        // Réplica do PDF do painel (mesmo PDFWriter/jsPDF do botão Baixar)
        if ($this->gerarPdfComPuppeteer($nome, $reportUrl, $destino)) {
            if ($this->pdfContemSecoesObrigatorias($destino)) {
                return $destino;
            }
            $this->log("[{$nome}] PDF Puppeteer sem panorama/assuntos — regenerando via Dompdf");
            @unlink($destino);
        }

        $this->log("[{$nome}] Fallback Dompdf (panorama + assuntos + gráficos)...");

        return $this->gerarPdfDoHtml($nome, $id, $html, $dtIniIso, $dtFimIso);
    }

    private function pdfContemSecoesObrigatorias(string $caminho): bool
    {
        if (! is_file($caminho) || filesize($caminho) < 500) {
            return false;
        }
        $bytes = file_get_contents($caminho);
        if ($bytes === false) {
            return false;
        }
        // jsPDF grava texto em literais PDF; aceita com ou sem acentos escapados
        $temPanorama = str_contains($bytes, 'Breve Panorama') || str_contains($bytes, 'Breve');
        $temAssuntos = str_contains($bytes, 'Performance por assunto')
            || str_contains($bytes, 'Performance por')
            || str_contains($bytes, 'tabela_questoes')
            || str_contains($bytes, 'Taxa de');

        return $temPanorama && $temAssuntos;
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
    private function gerarPdfComPuppeteer(string $nome, string $reportUrl, string $destino): bool
    {
        $script = function_exists('base_path')
            ? base_path('scripts/tutory-render-pdf.mjs')
            : dirname(__DIR__, 3) . '/scripts/tutory-render-pdf.mjs';

        if (! is_file($script)) {
            $this->log("[{$nome}] Script Puppeteer ausente: {$script}");

            return false;
        }

        $node = trim((string) env('NODE_BINARY', 'node')) ?: 'node';
        $cmd = [$node, $script, '--url', $reportUrl, '--out', $destino];
        $cookie = $this->cookieHeader();
        if ($cookie !== '') {
            $cmd[] = '--cookie';
            $cmd[] = $cookie;
        }
        if ($this->bearerToken !== null && $this->bearerToken !== '') {
            $cmd[] = '--token';
            $cmd[] = $this->bearerToken;
        }

        $this->log("[{$nome}] Renderizando PDF oficial (PDFWriter/jsPDF via Puppeteer)...");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = function_exists('base_path') ? base_path() : dirname($script, 2);
        $proc = @proc_open($cmd, $descriptors, $pipes, $cwd, null);
        if (! is_resource($proc)) {
            $this->log("[{$nome}] Não foi possível iniciar Node/Puppeteer");

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
            $this->log("[{$nome}] Puppeteer falhou (exit {$code}): " . $detail);

            return false;
        }

        $this->log("[{$nome}] Arquivo salvo: {$destino}");

        return true;
    }

    private function caminhoDestinoPdf(string $nomeAluno, ?string $id = null): string
    {
        $seguro = $this->sanitizarNomeArquivo($nomeAluno);
        $data = date('Ymd_Hi');
        // Formato: relatorio_$data_$aluno_$periodo.pdf
        $destino = $this->pastaDownload.'/relatorio_'.$data.'_'.$seguro.'_'.$this->periodo.'.pdf';
        $n = 1;
        while (file_exists($destino)) {
            $destino = $this->pastaDownload.'/relatorio_'.$data.'_'.$seguro.'_'.$this->periodo.'_'.$n.'.pdf';
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
     * Extrai o trecho do nome em filenames conhecidos.
     */
    private function extrairNomeDoArquivoPdf(string $arquivo): ?string
    {
        $periodo = preg_quote($this->periodo, '/');

        // relatorio_{Ymd}_{Hi}_{Nome}_{periodo}.pdf
        if (preg_match('/^relatorio_\d{8}_\d{4}_(.+)_'.$periodo.'(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return $m[1];
        }
        // legado: relatorio-{id}-{Nome}-{ddmmyyyy}.pdf
        if (preg_match('/^relatorio-\d+-(.+)-\d{8}(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return $m[1];
        }
        // legado: {Nome}_{Ym}.pdf
        if (preg_match('/^(.+)_\d{4}-\d{2}(?:_\d+)?\.pdf$/ui', $arquivo, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Localiza o PDF mais recente do aluno na pasta de download para o período atual.
     */
    private function encontrarPdfAluno(string $nomeAluno): ?string
    {
        if (! is_dir($this->pastaDownload)) {
            return null;
        }

        $seguro = $this->sanitizarNomeArquivo($nomeAluno);
        $candidatos = [];

        foreach (scandir($this->pastaDownload) ?: [] as $arquivo) {
            if (! str_ends_with(mb_strtolower($arquivo), '.pdf')) {
                continue;
            }
            $nomeArquivo = $this->extrairNomeDoArquivoPdf($arquivo);
            if ($nomeArquivo === null) {
                continue;
            }
            if (! $this->nomesArquivoSaoCompativeis($seguro, $nomeArquivo)) {
                continue;
            }
            $candidatos[] = $this->pastaDownload.'/'.$arquivo;
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $candidatos[0];
    }

    /**
     * Lista alunos do admin e envia o PDF por e-mail quando recebe_email=true.
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

        $enviados = 0;
        $pulados = 0;
        $falhas = 0;

        foreach ($alunos as $aluno) {
            $this->log("[{$aluno->nome}] e-mail={$aluno->email} | recebe_email=".($aluno->recebe_email ? 'sim' : 'não'));

            $pdf = $this->encontrarPdfAluno($aluno->nome);
            if ($pdf === null) {
                $this->log("[{$aluno->nome}] PDF não encontrado em {$this->pastaDownload}");
                $this->log("[{$aluno->nome}] Dica: o nome no admin deve coincidir com o do Tutory (ex.: Laíra Lacerda).");
                $falhas++;

                continue;
            }
            $encontrado = basename($pdf);
            $seguro = $this->sanitizarNomeArquivo($aluno->nome);
            if (! str_contains($encontrado, $seguro)) {
                $this->log("[{$aluno->nome}] PDF aproximado encontrado: {$encontrado}");
            } else {
                $this->log("[{$aluno->nome}] PDF: {$encontrado}");
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
                MailHelper::emailRelatorioCoach(
                    [
                        'nome' => $aluno->nome,
                        'periodoLabel' => $periodoLabel,
                    ],
                    $aluno->email,
                    $pdf
                );
                $this->log("[{$aluno->nome}] E-mail enviado para {$aluno->email}");
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

    private function gerarPdfDoHtml(
        string $nome,
        string $id,
        string $html,
        string $dtIniIso,
        string $dtFimIso,
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

        $pdfHtml = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family: DejaVu Sans, sans-serif; font-size:12px; color:#222; margin:24px;}
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
.chart{width:100%; max-width:700px; margin:8px 0 16px;}
.two-col{width:100%; border-collapse:collapse;}
.two-col td{width:50%; vertical-align:top; padding:4px;}
.assuntos{width:100%; border-collapse:collapse; margin-top:8px; font-size:11px;}
.assuntos thead td{background:#00aced; color:#fff; font-weight:bold; padding:8px;}
.assuntos tbody td{border-bottom:1px solid #eee; padding:8px; vertical-align:top;}
.assuntos .taxa{text-align:right; font-weight:bold;}
.rule{border:0;border-top:1px solid #eaeaea; margin:12px 0;}
</style></head><body>
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
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdfHtml, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $this->salvarBytesPdf($nome, $dompdf->output() ?? '', $id);
        } catch (Throwable $exc) {
            $this->log("[{$nome}] Erro Dompdf: " . $exc->getMessage());

            return null;
        }
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
        $out .= '<img class="chart" src="' . $img . '" />';

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

        $json = preg_replace('/([{\[,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $json) ?? $json;
        $json = str_replace("'", '"', $json);
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        // remove functions (datalabels formatter etc.)
        $json = preg_replace('/"formatter"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/"label"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/"function"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/function\s*\(.*?\)\s*\{.*?\},?/s', 'null,', $json) ?? $json;
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        return $this->aplicarDatalabelsOficiais($data, $canvasId);
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

        // Barras: nomes das disciplinas no eixo X
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
            $data['options']['legend']['display'] = false;
            $data['options']['plugins']['datalabels'] = [
                'color' => '#333',
                'anchor' => 'end',
                'align' => 'end',
                'offset' => 6,
                'font' => ['size' => 10],
                'formatter' => '__DATALABEL_PERCENT__',
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
                '"__DATALABEL_BUBBLE_PERCENT__"' => 'function(value){return value&&value.r!=null?Number(value.r*10).toFixed(0)+"%":"";}',
                // legado
                '"__DATALABEL_FORMATTER__"' => 'function(value){return Number(value).toFixed(0)+" questões";}',
            ];
            $chartJs = str_replace(array_keys($replacements), array_values($replacements), $chartJs);

            $payload = [
                'width' => 900,
                'height' => 420,
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

    private function salvarBytesPdf(string $nomeAluno, string $bytes, ?string $id = null): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $destino = $this->caminhoDestinoPdf($nomeAluno, $id);
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
            "Alunos: {$total}",
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
        $this->log('Processo iniciado em: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Modo: CLI/HTTP → cadastrar-relatorio-coach + Puppeteer/PDFWriter (fallback Dompdf)');

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

        /** @var array<string, array{nome: string, sucesso: bool, arquivo: ?string, tentativas: int, meta: array{nome: string, id: string}}> $resultados */
        $resultados = [];
        foreach ($alunos as $a) {
            $resultados[$a['nome']] = [
                'nome' => $a['nome'],
                'sucesso' => false,
                'arquivo' => null,
                'tentativas' => 0,
                'meta' => $a,
            ];
        }
        $nomes = array_keys($resultados);

        $processarLote = function (array $lista, int $rodada) use (&$resultados): void {
            $total = count($lista);
            foreach ($lista as $i => $nome) {
                $resultados[$nome]['tentativas']++;
                $t = $resultados[$nome]['tentativas'];
                $this->log(str_repeat('=', 50));
                $this->log("[rodada {$rodada}] Aluno " . ($i + 1) . "/{$total}: {$nome} (tentativa {$t}/" . self::MAX_TENTATIVAS . ')');
                try {
                    $arquivo = $this->processarAluno($resultados[$nome]['meta']);
                } catch (Throwable $exc) {
                    $this->log("[{$nome}] ERRO: " . $exc->getMessage());
                    $arquivo = null;
                }
                if ($arquivo !== null) {
                    $resultados[$nome]['sucesso'] = true;
                    $resultados[$nome]['arquivo'] = $arquivo;
                    $this->log("[{$nome}] SUCESSO");
                } else {
                    $resultados[$nome]['sucesso'] = false;
                    $resultados[$nome]['arquivo'] = null;
                    $this->log("[{$nome}] FALHA");
                }
            }
        };

        $processarLote($nomes, 1);
        for ($rodada = 2; $rodada <= self::MAX_TENTATIVAS; $rodada++) {
            $pendentes = array_values(array_filter($nomes, static fn($n) => ! $resultados[$n]['sucesso']));
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
        $lista = array_map(static fn($n) => $resultados[$n], $nomes);
        $fim = new \DateTimeImmutable('now');
        $log = $this->gravarLogResumo($inicio, $fim, count($nomes), $pdfs, $falhas, $lista);

        $this->log(str_repeat('=', 50));
        $this->log('Início: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Fim:    ' . $fim->format('d/m/Y H:i:s'));
        $this->log('Duração: ' . $this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()));
        $this->log('PDFs baixados: ' . count($pdfs));
        $this->log('Falhas finais: ' . count($falhas));
        foreach ($falhas as $f) {
            $this->log("- {$f} (tentativas: {$resultados[$f]['tentativas']})");
        }
        $this->log("Arquivos em: {$this->pastaDownload}");
        $this->log("Log salvo em: {$log}");
    }
}
