<?php

/**
 * Baixa o Relatório do Coach no Tutory para os alunos ATIVOS da consulta.
 *
 * Fluxo CLI/HTTP (sem browser):
 * 1. Login em /intent/login (sessão + Bearer token)
 * 2. GET/POST /alunos/consulta com status=ativos
 * 3. Para cada aluno: abre Relatório do Coach → gera com filtros → baixa PDF
 * 4. Reprocessa falhas (até 3 tentativas por aluno)
 *
 * Credenciais e pastas vêm do .env (veja .env.example).
 */

namespace App\Services\Tutory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CoachReportDownloader
{
    private const BASE = 'https://admin.tutory.com.br';

    private const URL_CONSULTA = self::BASE . '/alunos/consulta';

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
        $this->urlLogin = $loginUrl !== '' ? $loginUrl : self::BASE . '/login';
        $this->senha = '05473793150';
        $this->email = 'missaonomeacao';
        $pastaEnv = public_path('pdfs/');
        $this->pastaDownload = $this->expandHome(
            $pastaEnv !== ''
                ? $pastaEnv
                : rtrim((string) getenv('HOME'), '/') . '/Relatorios_Tutory'
        );
        $this->timeout = (int) (env('TIMEOUT') ?: 60);
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
                'Configure no .env: ' . implode(', ', $faltando) . '. Use .env.example como modelo.'
            );
        }
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
                'User-Agent' => 'MissaoNomeacao-TutoryCLI/1.0',
                'Accept' => 'application/json, text/html, */*;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => self::BASE,
                'Referer' => self::BASE . '/',
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

        $index = $this->client()->get('/index');
        $this->bearerToken = $this->extrairToken($index->body())
            ?? $this->extrairToken(json_encode($json) ?: '');

        if ($this->bearerToken !== null) {
            $this->log('Login realizado (Bearer token obtido)');
        } else {
            $this->log('Login realizado (sessão por cookie; token Bearer não encontrado no HTML)');
        }
    }

    private function extrairToken(string $html): ?string
    {
        if (preg_match('/adminUser\s*=\s*\{(.*?)\}\s*;/s', $html, $m)) {
            if (preg_match('/["\']token["\']\s*:\s*["\']([^"\']+)["\']/', $m[1], $t)) {
                return $t[1];
            }
        }
        // Delimitador # — a classe pode conter "/" (comum em JWTs/base64url)
        if (preg_match('#["\']token["\']\s*:\s*["\']([A-Za-z0-9._\-+/=]+)["\']#', $html, $t)) {
            return $t[1];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} [dataInicio, dataFim] Y-m-d
     */
    private function datasPeriodo(): array
    {
        $hoje = new \DateTimeImmutable('now');
        if ($this->periodo === '1') {
            return [$hoje->format('Y-m-01'), $hoje->format('Y-m-15')];
        }
        $ultimo = (int) $hoje->format('t');

        return [$hoje->format('Y-m-16'), $hoje->format('Y-m-') . str_pad((string) $ultimo, 2, '0', STR_PAD_LEFT)];
    }

    private function loadDom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new DOMXPath($dom);
    }

    /**
     * @return list<array{nome: string, id: ?string, href: ?string, attrs: array<string, string>}>
     */
    private function parseAlunosDaPagina(string $html): array
    {
        $xp = $this->loadDom($html);
        $cards = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' pesquisa-aluno-container ')]");
        $alunos = [];
        if ($cards === false) {
            return [];
        }

        /** @var DOMElement $card */
        foreach ($cards as $card) {
            $nomeNodes = $xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pesquisa-aluno-nome ')]", $card);
            $nome = '';
            if ($nomeNodes !== false && $nomeNodes->length > 0) {
                $nome = trim($nomeNodes->item(0)?->textContent ?? '');
            }
            if ($nome === '') {
                continue;
            }

            $link = null;
            $links = $xp->query(
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' btn-generate-report ')
                    or contains(translate(normalize-space(.), 'RELATÓRIO', 'relatorio'), 'relatorio do coach')
                    or contains(translate(normalize-space(.), 'RELATORIO', 'relatorio'), 'relatorio do coach')]",
                $card
            );
            $attrs = [];
            $href = null;
            $id = null;
            if ($links !== false && $links->length > 0) {
                /** @var DOMElement $link */
                $link = $links->item(0);
                foreach (['href', 'data-id', 'data-aluno', 'data-aluno-id', 'data-student', 'data-url', 'data-href', 'data-action', 'onclick'] as $attr) {
                    $val = $link->getAttribute($attr);
                    if ($val !== '') {
                        $attrs[$attr] = $val;
                    }
                }
                $href = $attrs['href'] ?? $attrs['data-url'] ?? $attrs['data-href'] ?? null;
                if ($href === '#' || $href === 'javascript:;' || $href === 'javascript:void(0)') {
                    $href = null;
                }
                $id = $attrs['data-id'] ?? $attrs['data-aluno'] ?? $attrs['data-aluno-id'] ?? $attrs['data-student'] ?? null;
                if ($id === null && isset($attrs['onclick']) && preg_match('/(\d{2,})/', $attrs['onclick'], $m)) {
                    $id = $m[1];
                }
                if ($id === null && is_string($href) && preg_match('/(?:aluno|id|student)[=\/](\d+)/i', $href, $m)) {
                    $id = $m[1];
                }
            }

            // fallback: qualquer data-id no card
            if ($id === null) {
                foreach (['data-id', 'data-aluno', 'data-aluno-id'] as $attr) {
                    $nodes = $xp->query('.//*[@' . $attr . ']', $card);
                    if ($nodes !== false && $nodes->length > 0) {
                        /** @var DOMElement $el */
                        $el = $nodes->item(0);
                        $id = $el->getAttribute($attr) ?: null;
                        if ($id !== null) {
                            break;
                        }
                    }
                }
            }

            $alunos[] = [
                'nome' => $nome,
                'id' => $id,
                'href' => $href,
                'attrs' => $attrs,
            ];
        }

        return $alunos;
    }

    /**
     * @return list<string> URLs absolutas de próximas páginas
     */
    private function parseLinksProximaPagina(string $html, string $paginaAtual): array
    {
        $xp = $this->loadDom($html);
        $urls = [];
        $queries = [
            "//li[contains(@class,'page-item') and not(contains(@class,'disabled'))]/a[@rel='next']",
            "//a[contains(@class,'page-link') and (@rel='next' or normalize-space()='›' or normalize-space()='»' or normalize-space()='>')]",
            "//a[contains(translate(normalize-space(.),'PRÓXIMOPROXIMO','proximoproximo'),'proximo')]",
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
                if ($href === '' || str_starts_with($href, '#')) {
                    continue;
                }
                $urls[] = $this->absolutizar($href, $paginaAtual);
            }
        }

        return array_values(array_unique($urls));
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

    private function filtrarAlunosAtivosHtml(): string
    {
        $this->log("Filtrando alunos com status 'ativos'...");

        // Alguns painéis usam GET, outros POST — tentamos ambos.
        $get = $this->client()->get('/alunos/consulta', ['status' => 'ativos']);
        $html = $get->body();
        if (str_contains($html, 'pesquisa-aluno-container')) {
            $this->log('Filtro aplicados via GET');

            return $html;
        }

        $post = $this->client()->asForm()->post('/alunos/consulta', ['status' => 'ativos']);
        $html = $post->body();
        if (str_contains($html, 'pesquisa-aluno-container')) {
            $this->log('Filtro aplicados via POST');

            return $html;
        }

        // Página base + formulário Buscar
        $base = $this->client()->get('/alunos/consulta');
        $htmlBase = $base->body();
        $action = '/alunos/consulta';
        $xp = $this->loadDom($htmlBase);
        $forms = $xp->query('//form[.//select[@name="status"] or .//input[@name="status"]]');
        if ($forms !== false && $forms->length > 0) {
            /** @var DOMElement $form */
            $form = $forms->item(0);
            $formAction = $form->getAttribute('data-action') ?: $form->getAttribute('action');
            if ($formAction !== '') {
                $action = $formAction;
            }
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            $payload = ['status' => 'ativos'];
            $inputs = $xp->query('.//input[@name]|.//select[@name]|.//textarea[@name]', $form);
            if ($inputs !== false) {
                foreach ($inputs as $input) {
                    if (! $input instanceof DOMElement) {
                        continue;
                    }
                    $name = $input->getAttribute('name');
                    if ($name === '' || $name === 'status') {
                        continue;
                    }
                    $type = strtolower($input->getAttribute('type'));
                    if (in_array($type, ['submit', 'button', 'image'], true)) {
                        continue;
                    }
                    $payload[$name] = $input->getAttribute('value');
                }
            }
            $resp = $method === 'POST'
                ? $this->client()->asForm()->post($action, $payload)
                : $this->client()->get($action, $payload);
            $html = $resp->body();
            if (str_contains($html, 'pesquisa-aluno-container')) {
                $this->log("Filtro aplicados via formulário ({$method} {$action})");

                return $html;
            }
        }

        throw new RuntimeException(
            'Não encontrei a lista de alunos ativos em /alunos/consulta. ' .
                'Confira LOGIN_USER/LOGIN_PASSWORD e se a conta tem acesso à pesquisa.'
        );
    }

    /**
     * @return list<array{nome: string, id: ?string, href: ?string, attrs: array<string, string>}>
     */
    private function coletarTodosAlunos(): array
    {
        $html = $this->filtrarAlunosAtivosHtml();
        $vistos = [];
        $alunos = [];
        $pagina = 1;
        $urlAtual = self::URL_CONSULTA . '?status=ativos';

        while (true) {
            $paginaAtual = $this->parseAlunosDaPagina($html);
            $this->log("Coletando página {$pagina}: " . count($paginaAtual) . ' aluno(s)');
            foreach ($paginaAtual as $aluno) {
                $chave = $aluno['id'] ?? $aluno['nome'];
                if (isset($vistos[$chave])) {
                    continue;
                }
                $vistos[$chave] = true;
                $alunos[] = $aluno;
            }

            $proximas = $this->parseLinksProximaPagina($html, $urlAtual);
            $proxima = null;
            foreach ($proximas as $cand) {
                if ($cand !== $urlAtual) {
                    $proxima = $cand;
                    break;
                }
            }
            if ($proxima === null) {
                break;
            }
            $this->log('Próxima página de alunos: ' . $proxima);
            $resp = $this->client()->get($proxima);
            $html = $resp->body();
            $urlAtual = $proxima;
            $pagina++;
            if ($pagina > 200) {
                $this->log('AVISO: limite de 200 páginas atingido');
                break;
            }
        }

        $this->log('Total de alunos encontrados: ' . count($alunos));

        return $alunos;
    }

    /**
     * @param  list<array{nome: string, id: ?string, href: ?string, attrs: array<string, string>}>  $alunos
     * @return list<array{nome: string, id: ?string, href: ?string, attrs: array<string, string>}>
     */
    private function filtrarAlunaTeste(array $alunos): array
    {
        $alvo = mb_strtolower(self::ALUNA_TESTE);
        foreach ($alunos as $aluno) {
            if (str_contains(mb_strtolower($aluno['nome']), $alvo)) {
                return [$aluno];
            }
        }

        return [];
    }

    /**
     * Extrai URL de relatório de um JSON arbitrário da API Tutory.
     *
     * @param  array<mixed>  $json
     */
    private function extrairUrlDoJson(array $json): ?string
    {
        $keys = ['url', 'link', 'href', 'report_url', 'relatorio', 'redirect', 'data'];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }
            $val = $json[$key];
            if (is_string($val) && (str_starts_with($val, 'http') || str_starts_with($val, '/'))) {
                return $this->absolutizar($val);
            }
            if (is_array($val)) {
                $nested = $this->extrairUrlDoJson($val);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        foreach ($json as $val) {
            if (is_array($val)) {
                $nested = $this->extrairUrlDoJson($val);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Descobre endpoint de geração a partir do HTML (data-action / forms / scripts).
     *
     * @return list<string>
     */
    private function descobrirEndpointsGeracao(string $html): array
    {
        $candidatos = [];
        if (preg_match_all('/\/intent\/[a-zA-Z0-9_\/-]*relat[a-zA-Z0-9_\/-]*/i', $html, $m)) {
            foreach ($m[0] as $path) {
                $candidatos[] = $path;
            }
        }
        if (preg_match_all('/\/intent\/[a-zA-Z0-9_\/-]*report[a-zA-Z0-9_\/-]*/i', $html, $m)) {
            foreach ($m[0] as $path) {
                $candidatos[] = $path;
            }
        }
        if (preg_match_all('/\/intent\/[a-zA-Z0-9_\/-]*coach[a-zA-Z0-9_\/-]*/i', $html, $m)) {
            foreach ($m[0] as $path) {
                $candidatos[] = $path;
            }
        }
        $xp = $this->loadDom($html);
        $nodes = $xp->query("//a[contains(@class,'btn-generate-my-report')]|//*[@data-action]|//form[@data-action]");
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                foreach (['data-action', 'href', 'action'] as $attr) {
                    $val = $node->getAttribute($attr);
                    if ($val !== '' && str_contains($val, '/intent/')) {
                        $candidatos[] = parse_url($this->absolutizar($val), PHP_URL_PATH) ?: $val;
                    }
                }
            }
        }

        $envEndpoint = trim((string) env('TUTORY_REPORT_GENERATE_URL', ''));
        if ($envEndpoint !== '') {
            array_unshift($candidatos, $envEndpoint);
        }

        // Fallbacks comuns observados em painéis Tutory/mentoria
        $candidatos = array_merge($candidatos, [
            '/intent/gerar-relatorio-coach',
            '/intent/relatorio-coach',
            '/intent/generate-report',
            '/intent/gerar-relatorio',
            '/intent/aluno/relatorio',
        ]);

        return array_values(array_unique($candidatos));
    }

    /**
     * @param  array{nome: string, id: ?string, href: ?string, attrs: array<string, string>}  $aluno
     */
    private function processarAluno(array $aluno): ?string
    {
        $nome = $aluno['nome'];
        [$dataInicio, $dataFim] = $this->datasPeriodo();
        $this->log("[{$nome}] Datas: {$dataInicio} → {$dataFim}");

        $htmlContexto = '';
        if (! empty($aluno['href'])) {
            $this->log("[{$nome}] Abrindo href do relatório...");
            $htmlContexto = $this->client()->get($aluno['href'])->body();
        } else {
            // Reabre a consulta (garante contexto de sessão/modais embutidos na página)
            $htmlContexto = $this->client()->get('/alunos/consulta', ['status' => 'ativos'])->body();
        }

        $endpoints = $this->descobrirEndpointsGeracao($htmlContexto);
        $this->log("[{$nome}] Endpoints candidatos: " . implode(', ', array_slice($endpoints, 0, 6)));

        $payloadBase = [
            'tipo' => 'questoes',
            'type' => 'questoes',
            'periodo' => 'mes',
            'period' => 'mes',
            'data_ini' => $dataInicio,
            'data_fim' => $dataFim,
            'relDataIni' => $dataInicio,
            'relDataFim' => $dataFim,
            'ini' => $dataInicio,
            'fim' => $dataFim,
            'filtro' => 'questoes',
            'agrupamento' => 'mes',
        ];
        if (! empty($aluno['id'])) {
            $payloadBase['id'] = $aluno['id'];
            $payloadBase['aluno'] = $aluno['id'];
            $payloadBase['aluno_id'] = $aluno['id'];
            $payloadBase['id_aluno'] = $aluno['id'];
        }
        foreach ($aluno['attrs'] as $k => $v) {
            if (str_starts_with($k, 'data-') && $k !== 'data-action') {
                $payloadBase[substr($k, 5)] = $v;
            }
        }

        $reportUrl = null;
        $ultimaResposta = '';
        foreach ($endpoints as $endpoint) {
            try {
                $path = str_starts_with($endpoint, 'http')
                    ? $endpoint
                    : $endpoint;
                $resp = $this->client()->asForm()->post($path, $payloadBase);
                $ultimaResposta = substr($resp->body(), 0, 500);
                if ($resp->status() === 404) {
                    continue;
                }
                $json = $resp->json();
                if (is_array($json)) {
                    if (! empty($json['error']) && empty($json['result'])) {
                        $this->log("[{$nome}] {$endpoint}: " . $json['error']);
                        continue;
                    }
                    $reportUrl = $this->extrairUrlDoJson($json);
                    if ($reportUrl !== null) {
                        $this->log("[{$nome}] Relatório gerado via {$endpoint}");
                        break;
                    }
                    // Alguns retornos só trazem HTML embutido / id
                    if (! empty($json['result']) && ! empty($json['html'])) {
                        $reportUrl = $this->salvarPdfDeHtml($nome, (string) $json['html']);
                        if ($reportUrl !== null) {
                            return $reportUrl;
                        }
                    }
                }

                $ct = strtolower((string) $resp->header('Content-Type'));
                if (str_contains($ct, 'pdf') || str_starts_with($resp->body(), '%PDF')) {
                    return $this->salvarBytesPdf($nome, $resp->body());
                }

                // Resposta HTML com link de acesso
                if (str_contains($resp->body(), 'btn_save') || str_contains($resp->body(), 'chart_questoes_dia')) {
                    $baixado = $this->baixarPdfDaPaginaRelatorio($nome, $resp->body(), $this->absolutizar($path));
                    if ($baixado !== null) {
                        return $baixado;
                    }
                }
            } catch (Throwable $exc) {
                $this->log("[{$nome}] {$endpoint} erro: " . $exc->getMessage());
            }
        }

        if ($reportUrl === null && ! empty($aluno['href'])) {
            $reportUrl = $this->absolutizar((string) $aluno['href']);
        }

        if ($reportUrl === null) {
            $this->log("[{$nome}] Não foi possível gerar/localizar URL do relatório. Última resposta: {$ultimaResposta}");

            return null;
        }

        $this->log("[{$nome}] Acessando relatório: {$reportUrl}");
        $pagina = $this->client()
            ->withHeaders(['Accept' => 'text/html,application/pdf,*/*'])
            ->get($reportUrl);

        $ct = strtolower((string) $pagina->header('Content-Type'));
        if (str_contains($ct, 'pdf') || str_starts_with($pagina->body(), '%PDF')) {
            return $this->salvarBytesPdf($nome, $pagina->body());
        }

        return $this->baixarPdfDaPaginaRelatorio($nome, $pagina->body(), $reportUrl);
    }

    private function baixarPdfDaPaginaRelatorio(string $nome, string $html, string $paginaUrl): ?string
    {
        // 1) links diretos para PDF
        if (preg_match_all('/href=["\']([^"\']+\.pdf[^"\']*)["\']/i', $html, $m)) {
            foreach ($m[1] as $href) {
                $url = $this->absolutizar($href, $paginaUrl);
                $pdf = $this->client()->get($url);
                if (str_starts_with($pdf->body(), '%PDF')) {
                    $this->log("[{$nome}] PDF via link {$url}");

                    return $this->salvarBytesPdf($nome, $pdf->body());
                }
            }
        }

        // 2) data-url / onclick no #btn_save
        $xp = $this->loadDom($html);
        $btn = $xp->query("//*[@id='btn_save']")->item(0);
        if ($btn instanceof DOMElement) {
            foreach (['data-url', 'data-href', 'href', 'data-file', 'data-download'] as $attr) {
                $val = $btn->getAttribute($attr);
                if ($val === '') {
                    continue;
                }
                $url = $this->absolutizar($val, $paginaUrl);
                $pdf = $this->client()->get($url);
                if (str_starts_with($pdf->body(), '%PDF') || str_contains(strtolower((string) $pdf->header('Content-Type')), 'pdf')) {
                    $this->log("[{$nome}] PDF via #btn_save[{$attr}]");

                    return $this->salvarBytesPdf($nome, $pdf->body());
                }
            }
        }

        // 3) endpoints /intent de download citados na página
        if (preg_match_all('/\/intent\/[a-zA-Z0-9_\/-]*(download|baixar|pdf|export)[a-zA-Z0-9_\/-]*/i', $html, $m)) {
            foreach (array_unique($m[0]) as $path) {
                $pdf = $this->client()->asForm()->post($path, []);
                if (str_starts_with($pdf->body(), '%PDF')) {
                    $this->log("[{$nome}] PDF via {$path}");

                    return $this->salvarBytesPdf($nome, $pdf->body());
                }
            }
        }

        $envDownload = trim((string) env('TUTORY_REPORT_DOWNLOAD_URL', ''));
        if ($envDownload !== '') {
            $pdf = $this->client()->get($envDownload);
            if (str_starts_with($pdf->body(), '%PDF')) {
                return $this->salvarBytesPdf($nome, $pdf->body());
            }
        }

        $this->log(
            "[{$nome}] Página do relatório aberta, mas não há endpoint HTTP de PDF " .
                '(o botão Baixar do painel gera o arquivo no navegador). ' .
                'Defina TUTORY_REPORT_GENERATE_URL / TUTORY_REPORT_DOWNLOAD_URL no .env se souber as rotas.'
        );

        // Guarda HTML para inspeção manual
        $dump = $this->pastaDownload . '/debug_' . preg_replace('/\s+/', '_', $nome) . '.html';
        file_put_contents($dump, $html);
        $this->log("[{$nome}] HTML salvo em: {$dump}");

        return null;
    }

    private function salvarBytesPdf(string $nomeAluno, string $bytes): string
    {
        $seguro = preg_replace('/[^A-Za-z0-9 ._\\-]/u', '_', $nomeAluno) ?? 'aluno';
        $seguro = trim(str_replace(' ', '_', $seguro)) ?: 'aluno';
        $mes = date('Y-m');
        $destino = $this->pastaDownload . '/' . $seguro . '_' . $mes . '.pdf';
        $contador = 1;
        while (file_exists($destino)) {
            $destino = $this->pastaDownload . '/' . $seguro . '_' . $mes . '_' . $contador . '.pdf';
            $contador++;
        }
        file_put_contents($destino, $bytes);
        $this->log("[{$nomeAluno}] Arquivo salvo: {$destino}");

        return $destino;
    }

    private function salvarPdfDeHtml(string $nome, string $html): ?string
    {
        // Sem engine de browser: só salva HTML de debug (PDF real vem do download HTTP).
        $dump = $this->pastaDownload . '/debug_' . preg_replace('/\s+/', '_', $nome) . '_embed.html';
        file_put_contents($dump, $html);
        $this->log("[{$nome}] HTML embutido salvo em: {$dump}");

        return null;
    }

    private function formatarDuracao(float $segundos): string
    {
        $total = (int) round($segundos);
        $horas = intdiv($total, 3600);
        $resto = $total % 3600;
        $minutos = intdiv($resto, 60);
        $segs = $resto % 60;
        if ($horas > 0) {
            return "{$horas}h {$minutos}min {$segs}s";
        }
        if ($minutos > 0) {
            return "{$minutos}min {$segs}s";
        }

        return "{$segs}s";
    }

    /**
     * @param  list<string>  $pdfs
     * @param  list<string>  $falhas
     * @param  list<array{nome: string, sucesso: bool, tentativas: int}>|null  $resultados
     */
    private function gravarLogResumo(
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        int $totalAlunos,
        array $pdfs,
        array $falhas,
        ?array $resultados = null,
    ): string {
        $caminho = $this->pastaDownload . '/log_download_' . $inicio->format('Ymd_His') . '.txt';
        $linhas = [
            'Relatórios Tutory - resumo da execução (CLI/HTTP)',
            'Início: ' . $inicio->format('d/m/Y H:i:s'),
            'Fim:    ' . $fim->format('d/m/Y H:i:s'),
            'Duração: ' . $this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()),
            "Alunos processados: {$totalAlunos}",
            'PDFs baixados: ' . count($pdfs),
            'Falhas finais: ' . count($falhas),
            "Pasta: {$this->pastaDownload}",
            '',
            'Arquivos:',
        ];
        if ($pdfs !== []) {
            foreach ($pdfs as $p) {
                $linhas[] = '- ' . basename($p);
            }
        } else {
            $linhas[] = '- (nenhum)';
        }

        $linhas[] = '';
        $linhas[] = 'Status por aluno:';
        if ($resultados !== null && $resultados !== []) {
            foreach ($resultados as $r) {
                $status = $r['sucesso'] ? 'OK' : 'FALHA';
                $linhas[] = "- [{$status}] {$r['nome']} (tentativas: {$r['tentativas']})";
            }
        } else {
            $linhas[] = '- (nenhum)';
        }

        if ($falhas !== []) {
            $linhas[] = '';
            $linhas[] = 'Alunos com falha após todas as tentativas:';
            foreach ($falhas as $nome) {
                $linhas[] = "- {$nome}";
            }
        }

        file_put_contents($caminho, implode("\n", $linhas) . "\n");

        return $caminho;
    }

    private function baixarTodos(): void
    {
        $inicio = new \DateTimeImmutable('now');
        $this->log('Processo iniciado em: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Modo: CLI/HTTP (sem Selenium/Firefox)');

        $alunos = $this->coletarTodosAlunos();
        if ($this->teste) {
            $alunos = $this->filtrarAlunaTeste($alunos);
            if ($alunos !== []) {
                $this->log('Modo --teste: processando apenas ' . $alunos[0]['nome']);
            } else {
                $this->log("Modo --teste: aluna '" . self::ALUNA_TESTE . "' não encontrada na lista.");
            }
        }

        if ($alunos === []) {
            $fim = new \DateTimeImmutable('now');
            $this->log('Nenhum aluno encontrado em /alunos/consulta.');
            $log = $this->gravarLogResumo($inicio, $fim, 0, [], [], []);
            $this->log("Log salvo em: {$log}");

            return;
        }

        /** @var array<string, array{nome: string, sucesso: bool, arquivo: ?string, tentativas: int, meta: array<string, mixed>}> $resultados */
        $resultados = [];
        foreach ($alunos as $aluno) {
            $resultados[$aluno['nome']] = [
                'nome' => $aluno['nome'],
                'sucesso' => false,
                'arquivo' => null,
                'tentativas' => 0,
                'meta' => $aluno,
            ];
        }
        $nomes = array_keys($resultados);

        $processarLote = function (array $lista, int $rodada) use (&$resultados): void {
            $total = count($lista);
            foreach ($lista as $i => $nome) {
                $resultados[$nome]['tentativas']++;
                $tentativa = $resultados[$nome]['tentativas'];
                $n = $i + 1;
                $this->log(str_repeat('=', 50));
                $this->log(
                    "[rodada {$rodada}] Aluno {$n}/{$total}: {$nome} " .
                        '(tentativa ' . $tentativa . '/' . self::MAX_TENTATIVAS . ')'
                );
                try {
                    /** @var array{nome: string, id: ?string, href: ?string, attrs: array<string, string>} $meta */
                    $meta = $resultados[$nome]['meta'];
                    $arquivo = $this->processarAluno($meta);
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
            $pendentes = array_values(array_filter(
                $nomes,
                static fn(string $n) => ! $resultados[$n]['sucesso']
            ));
            if ($pendentes === []) {
                $this->log(str_repeat('=', 50));
                $this->log('Nenhuma falha restante — sem reprocessamento.');
                break;
            }
            $this->log(str_repeat('=', 50));
            $this->log(
                'Reprocessando ' . count($pendentes) . ' aluno(s) com erro ' .
                    '(rodada ' . $rodada . '/' . self::MAX_TENTATIVAS . ')...'
            );
            $processarLote($pendentes, $rodada);
        }

        $pdfs = [];
        $falhas = [];
        foreach ($resultados as $r) {
            if ($r['sucesso'] && $r['arquivo'] !== null) {
                $pdfs[] = $r['arquivo'];
            }
            if (! $r['sucesso']) {
                $falhas[] = $r['nome'];
            }
        }
        $listaResultados = array_map(static fn(string $n) => $resultados[$n], $nomes);

        $fim = new \DateTimeImmutable('now');
        $log = $this->gravarLogResumo($inicio, $fim, count($nomes), $pdfs, $falhas, $listaResultados);

        $this->log(str_repeat('=', 50));
        $this->log('Início: ' . $inicio->format('d/m/Y H:i:s'));
        $this->log('Fim:    ' . $fim->format('d/m/Y H:i:s'));
        $this->log('Duração: ' . $this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()));
        $this->log('PDFs baixados: ' . count($pdfs));
        $this->log('Falhas finais: ' . count($falhas));
        if ($falhas !== []) {
            $this->log('Alunos com falha:');
            foreach ($falhas as $nome) {
                $this->log("- {$nome} (tentativas: {$resultados[$nome]['tentativas']})");
            }
        }
        $this->log("Arquivos em: {$this->pastaDownload}");
        $this->log("Log salvo em: {$log}");
    }
}
