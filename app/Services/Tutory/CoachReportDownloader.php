<?php

/**
 * Baixa o Relatório do Coach no Tutory para alunos ATIVOS (CLI/HTTP).
 *
 * 1. Login (/intent/login) → sessão + Bearer
 * 2. /alunos/consulta?status=ativos (cards com data-id)
 * 3. POST /intent/cadastrar-relatorio-coach
 * 4. GET /documentos/relatorios/questoes?key=...
 * 5. Gera PDF via Dompdf (gráficos via QuickChart a partir do Chart.js embutido)
 * 6. Reprocessa falhas (até 3x)
 */

namespace App\Services\Tutory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CoachReportDownloader
{
    private const BASE = 'https://admin.tutory.com.br';

    private const ALUNA_TESTE = 'Marianny Carvalho';

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
            echo $message.PHP_EOL;
        };

        $loginUrl = trim((string) env('LOGIN_URL', ''));
        $this->urlLogin = $loginUrl !== '' ? $loginUrl : self::BASE.'/login';
        $this->email = trim((string) env('LOGIN_USER', ''));
        $this->senha = trim((string) env('LOGIN_PASSWORD', ''));

        $pastaEnv = trim((string) env('PASTA_DOWNLOAD', ''));
        $defaultPasta = function_exists('storage_path')
            ? storage_path('app/tutory-relatorios')
            : rtrim((string) getenv('HOME'), '/').'/Relatorios_Tutory';
        $this->pastaDownload = $this->expandHome($pastaEnv !== '' ? $pastaEnv : $defaultPasta);

        $this->timeout = (int) (env('TIMEOUT') ?: 60);
        $this->cookieFile = sys_get_temp_dir().'/tutory_cookies_'.getmypid().'.json';
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
        $faltando = [];
        if ($this->email === '') {
            $faltando[] = 'LOGIN_USER';
        }
        if ($this->senha === '') {
            $faltando[] = 'LOGIN_PASSWORD';
        }
        if ($faltando !== []) {
            throw new RuntimeException(
                'Configure no .env: '.implode(', ', $faltando).'. Use .env.example como modelo.'
            );
        }
        if (! in_array($this->periodo, ['1', '2'], true)) {
            throw new RuntimeException('--periodo deve ser 1 ou 2.');
        }
    }

    private function expandHome(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return rtrim((string) getenv('HOME'), '/').substr($path, 1);
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
                'Referer' => self::BASE.'/alunos/consulta',
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
            throw new RuntimeException('Não foi possível abrir a página de login (HTTP '.$loginPage->status().').');
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
            throw new RuntimeException('Falha no login: '.$erro);
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

        return [$hoje->format('Y-m-16'), $hoje->format('Y-m-').str_pad((string) $ultimo, 2, '0', STR_PAD_LEFT)];
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
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

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
            $this->log('Intents na página: '.implode(', ', $this->endpointsGeracao));
        }

        $alunos = [];
        $vistos = [];
        $pagina = 1;
        $urlAtual = self::BASE.'/alunos/consulta?status=ativos';

        while (true) {
            $paginaAlunos = $this->parseAlunosDaPagina($html);
            $this->log("Coletando página {$pagina}: ".count($paginaAlunos).' aluno(s)');
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
            $this->log('Próxima página: '.$proxima);
            $html = $this->client()->get($proxima)->body();
            $urlAtual = $proxima;
            $pagina++;
            if ($pagina > 100) {
                break;
            }
        }

        $this->log('Total de alunos ativos: '.count($alunos));

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
            return 'https:'.$url;
        }
        if (str_starts_with($url, '/')) {
            return self::BASE.$url;
        }

        return rtrim($base, '/').'/'.ltrim($url, '/');
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
        // Backend PHP espera alunos[]=ID (como o jQuery do painel)
        $body = 'alunos[]='.rawurlencode($id)
            .'&dt_ini='.rawurlencode($dtIni)
            .'&dt_fim='.rawurlencode($dtFim)
            .'&agrupamento=mes';

        $resp = $this->client()
            ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'])
            ->withBody($body, 'application/x-www-form-urlencoded; charset=UTF-8')
            ->post($endpoint);

        $json = $resp->json();
        if (! is_array($json) || empty($json['result'])) {
            $erro = is_array($json) ? (string) ($json['error'] ?? $resp->body()) : $resp->body();
            $this->log("[{$nome}] Falha ao gerar: ".$erro);

            return null;
        }

        $lista = $json['data'] ?? null;
        if (! is_array($lista) || $lista === [] || empty($lista[0]['token'])) {
            $this->log("[{$nome}] Resposta sem token de relatório: ".$resp->body());

            return null;
        }

        $key = (string) $lista[0]['token'];
        $model = trim((string) env('TUTORY_REPORT_MODEL', 'questoes')) ?: 'questoes';
        $reportUrl = self::BASE.'/documentos/relatorios/'.$model.'?key='.rawurlencode($key);
        $this->log("[{$nome}] Abrindo relatório...");

        $pagina = $this->client()
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get('/documentos/relatorios/'.$model, ['key' => $key]);

        if ($pagina->status() >= 400 || ! str_contains($pagina->body(), 'btn_save')) {
            $this->log("[{$nome}] Página do relatório inválida (HTTP {$pagina->status()})");

            return null;
        }

        return $this->gerarPdfDoHtml($nome, $id, $pagina->body(), $dtIniIso, $dtFimIso);
    }

    private function gerarPdfDoHtml(
        string $nome,
        string $id,
        string $html,
        string $dtIniIso,
        string $dtFimIso,
    ): ?string {
        $this->log("[{$nome}] Montando PDF (Dompdf + gráficos QuickChart)...");

        $xp = $this->loadDom($html);
        $periodo = $this->xpathText($xp, "//*[contains(@class,'report-header')]//p")
            ?: "Período do relatório: de {$dtIniIso} a {$dtFimIso}";
        $curso = $this->xpathText($xp, "//*[contains(@class,'report-aluno-desc')]//p")
            ?: '';

        $chartsHtml = '';
        if (extension_loaded('gd')) {
            $chartIds = [
                'chart_questoes_dia' => 'Acertos e Erros',
                'chart_bolha_questoes' => 'Bolha de Questões',
                'chart_top_melhores' => 'Top Melhores',
                'chart_top_piores' => 'Top Piores',
                'chart_evolucao_materia' => 'Evolução por Matéria',
                'chart_questoes_disciplina' => 'Questões por Disciplina',
            ];
            foreach ($chartIds as $chartId => $titulo) {
                $cfg = $this->extrairChartConfig($html, $chartId);
                if ($cfg === null) {
                    continue;
                }
                $img = $this->quickChartPngDataUri($cfg);
                if ($img === null) {
                    continue;
                }
                $chartsHtml .= '<h3 style="color:#00aced;margin:18px 0 8px;">'.htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8').'</h3>';
                $chartsHtml .= '<img src="'.$img.'" style="width:100%;max-width:700px;" />';
            }
        } else {
            $this->log("[{$nome}] AVISO: extensão GD ausente — PDF sem imagens de gráfico");
            $chartsHtml = '<p style="color:#888;">(Gráficos omitidos: instale php-gd no servidor)</p>';
        }

        $seguroNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $seguroCurso = htmlspecialchars($curso, ENT_QUOTES, 'UTF-8');
        $seguroPeriodo = htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8');

        $pdfHtml = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family: DejaVu Sans, sans-serif; font-size:12px; color:#222; margin:24px;}
h1{font-size:20px; margin:0 0 6px;}
h3{font-size:14px;}
.sub{color:#555; margin-bottom:16px;}
.rule{border:0;border-top:1px solid #eaeaea; margin:12px 0;}
</style></head><body>
<h1>Relatório de Questões</h1>
<div class="sub">{$seguroPeriodo}</div>
<hr class="rule" />
<h2 style="margin:0 0 4px;">{$seguroNome}</h2>
<div class="sub">{$seguroCurso}</div>
{$chartsHtml}
<p style="margin-top:24px;color:#888;font-size:10px;">Gerado via CLI HTTP · aluno id {$id}</p>
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

            return $this->salvarBytesPdf($nome, $dompdf->output() ?? '');
        } catch (Throwable $exc) {
            $this->log("[{$nome}] Erro Dompdf: ".$exc->getMessage());

            return null;
        }
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
        // new Chart(elChartQuestoesDia, { ... });
        // ou new Chart(document.getElementById('chart_...'), { ... });
        $patterns = [
            '/new\s+Chart\s*\(\s*[^,]+,\s*(\{.*?)\s*\)\s*;\s*(?:var\s+chart|\$\("#btn_save"\)|var\s+elChart|<\/script>)/s',
        ];

        // Mais preciso: achar getElementById('id') e o new Chart seguinte
        $pos = strpos($html, "getElementById('{$canvasId}')");
        if ($pos === false) {
            $pos = strpos($html, 'getElementById("'.$canvasId.'")');
        }
        if ($pos === false) {
            return null;
        }

        $slice = substr($html, $pos, 12000);
        if (! preg_match('/new\s+Chart\s*\(\s*[^,]+,\s*(\{)/', $slice, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = (int) $m[1][1];
        $jsonish = $this->extrairObjetoJsBalanceado($slice, $start);
        if ($jsonish === null) {
            return null;
        }

        // JS object → JSON aproximado
        $json = $jsonish;
        $json = preg_replace('/([{\[,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $json) ?? $json;
        $json = str_replace("'", '"', $json);
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        // remove functions (datalabels formatter etc.)
        $json = preg_replace('/"formatter"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/"function"\s*:\s*function\s*\(.*?\)\s*\{.*?\},?/s', '', $json) ?? $json;
        $json = preg_replace('/function\s*\(.*?\)\s*\{.*?\},?/s', 'null,', $json) ?? $json;
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;

        $data = json_decode($json, true);
        if (! is_array($data)) {
            // fallback mínimo só com type/data se parse falhar
            if (preg_match('/type:\s*[\'"](\w+)[\'"]/', $jsonish, $tm)
                && preg_match('/labels:\s*(\[[^\]]*\])/', $jsonish, $lm)
            ) {
                return [
                    'type' => $tm[1],
                    'data' => [
                        'labels' => json_decode(str_replace("'", '"', $lm[1]), true) ?: [],
                        'datasets' => [],
                    ],
                    'options' => ['plugins' => ['legend' => ['display' => true]]],
                ];
            }

            return null;
        }

        // QuickChart usa Chart.js v3+ em parte; simplifica options
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
            $resp = Http::timeout(30)
                ->asJson()
                ->post('https://quickchart.io/chart', [
                    'width' => 800,
                    'height' => 400,
                    'format' => 'png',
                    'backgroundColor' => 'white',
                    'chart' => $chartConfig,
                ]);
            if ($resp->successful() && strlen($resp->body()) > 100) {
                return 'data:image/png;base64,'.base64_encode($resp->body());
            }

            // GET fallback
            $url = 'https://quickchart.io/chart?c='.rawurlencode(json_encode($chartConfig, JSON_UNESCAPED_UNICODE) ?: '{}').'&w=800&h=400&bkg=white&f=png';
            $get = Http::timeout(30)->get($url);
            if ($get->successful() && strlen($get->body()) > 100) {
                return 'data:image/png;base64,'.base64_encode($get->body());
            }
        } catch (Throwable $exc) {
            $this->log('QuickChart erro: '.$exc->getMessage());
        }

        return null;
    }

    private function salvarBytesPdf(string $nomeAluno, string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $seguro = preg_replace('/[^A-Za-z0-9 ._\\-]/u', '_', $nomeAluno) ?? 'aluno';
        $seguro = trim(str_replace(' ', '_', $seguro)) ?: 'aluno';
        $mes = date('Y-m');
        $destino = $this->pastaDownload.'/'.$seguro.'_'.$mes.'.pdf';
        $n = 1;
        while (file_exists($destino)) {
            $destino = $this->pastaDownload.'/'.$seguro.'_'.$mes.'_'.$n.'.pdf';
            $n++;
        }
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
        $caminho = $this->pastaDownload.'/log_download_'.$inicio->format('Ymd_His').'.txt';
        $linhas = [
            'Relatórios Tutory - CLI/HTTP',
            'Início: '.$inicio->format('d/m/Y H:i:s'),
            'Fim:    '.$fim->format('d/m/Y H:i:s'),
            'Duração: '.$this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()),
            "Alunos: {$total}",
            'PDFs: '.count($pdfs),
            'Falhas: '.count($falhas),
            "Pasta: {$this->pastaDownload}",
            '',
            'Arquivos:',
        ];
        $linhas = array_merge($linhas, $pdfs !== [] ? array_map(static fn ($p) => '- '.basename($p), $pdfs) : ['- (nenhum)']);
        $linhas[] = '';
        $linhas[] = 'Status:';
        foreach ($resultados as $r) {
            $linhas[] = '- ['.($r['sucesso'] ? 'OK' : 'FALHA')."] {$r['nome']} (tentativas: {$r['tentativas']})";
        }
        if ($falhas !== []) {
            $linhas[] = '';
            $linhas[] = 'Falhas finais:';
            foreach ($falhas as $f) {
                $linhas[] = "- {$f}";
            }
        }
        file_put_contents($caminho, implode("\n", $linhas)."\n");

        return $caminho;
    }

    private function baixarTodos(): void
    {
        $inicio = new \DateTimeImmutable('now');
        $this->log('Processo iniciado em: '.$inicio->format('d/m/Y H:i:s'));
        $this->log('Modo: CLI/HTTP → /intent/cadastrar-relatorio-coach + Dompdf');

        $alunos = $this->coletarAlunosAtivos();
        if ($this->teste) {
            $alvo = mb_strtolower(self::ALUNA_TESTE);
            $alunos = array_values(array_filter(
                $alunos,
                static fn (array $a) => str_contains(mb_strtolower($a['nome']), $alvo)
            ));
            if ($alunos !== []) {
                $this->log('Modo --teste: '.$alunos[0]['nome'].' (id '.$alunos[0]['id'].')');
            } else {
                $this->log("Modo --teste: '".self::ALUNA_TESTE."' não encontrada.");
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
                $this->log("[rodada {$rodada}] Aluno ".($i + 1)."/{$total}: {$nome} (tentativa {$t}/".self::MAX_TENTATIVAS.')');
                try {
                    $arquivo = $this->processarAluno($resultados[$nome]['meta']);
                } catch (Throwable $exc) {
                    $this->log("[{$nome}] ERRO: ".$exc->getMessage());
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
            $pendentes = array_values(array_filter($nomes, static fn ($n) => ! $resultados[$n]['sucesso']));
            if ($pendentes === []) {
                $this->log(str_repeat('=', 50));
                $this->log('Nenhuma falha restante.');
                break;
            }
            $this->log(str_repeat('=', 50));
            $this->log('Reprocessando '.count($pendentes)." falha(s) (rodada {$rodada}/".self::MAX_TENTATIVAS.')...');
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
        $lista = array_map(static fn ($n) => $resultados[$n], $nomes);
        $fim = new \DateTimeImmutable('now');
        $log = $this->gravarLogResumo($inicio, $fim, count($nomes), $pdfs, $falhas, $lista);

        $this->log(str_repeat('=', 50));
        $this->log('Início: '.$inicio->format('d/m/Y H:i:s'));
        $this->log('Fim:    '.$fim->format('d/m/Y H:i:s'));
        $this->log('Duração: '.$this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()));
        $this->log('PDFs baixados: '.count($pdfs));
        $this->log('Falhas finais: '.count($falhas));
        foreach ($falhas as $f) {
            $this->log("- {$f} (tentativas: {$resultados[$f]['tentativas']})");
        }
        $this->log("Arquivos em: {$this->pastaDownload}");
        $this->log("Log salvo em: {$log}");
    }
}
