<?php

/**
 * Baixa o Relatório do Coach no Tutory para os alunos ATIVOS da consulta.
 *
 * Fluxo por aluno (igual ao script Python):
 * 1. Login
 * 2. Alunos → Pesquisa (/alunos/consulta)
 * 3. Filtro status = ativos + Buscar
 * 4. Opções do aluno → Relatório do Coach
 * 5. Filtros (questões + mês + datas) → Gerar
 * 6. Acessar Relatório → Baixar
 * 7. Fecha a aba do relatório e passa para o próximo aluno
 * 8. Ao fim, reprocessa falhas (até 3 tentativas por aluno)
 *
 * Credenciais e pastas vêm do .env (veja .env.example).
 */

namespace App\Services\Tutory;

use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Firefox\FirefoxDriver;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverWait;
use RuntimeException;
use Throwable;

class CoachReportDownloader
{
    private const URL_CONSULTA = 'https://admin.tutory.com.br/alunos/consulta';

    private const ALUNA_TESTE = 'Marianny Carvalho';

    private const MAX_TENTATIVAS = 3;

    private string $urlLogin;

    private string $email;

    private string $senha;

    private string $pastaDownload;

    private string $firefoxProfile;

    private string $firefoxBinary;

    private bool $headless;

    private int $timeout;

    private int $downloadTimeout;

    private int $chartWait;

    private string $periodo;

    private bool $teste;

    private ?RemoteWebDriver $driver = null;

    private ?WebDriverWait $wait = null;

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
        $this->urlLogin = $loginUrl !== '' ? $loginUrl : 'https://admin.tutory.com.br/login';
        $this->email = trim((string) env('LOGIN_USER', ''));
        $this->senha = trim((string) env('LOGIN_PASSWORD', ''));
        $pastaEnv = trim((string) env('PASTA_DOWNLOAD', ''));
        $this->pastaDownload = $this->expandHome(
            $pastaEnv !== ''
                ? $pastaEnv
                : rtrim((string) getenv('HOME'), '/').'/Relatorios_Tutory'
        );
        $this->firefoxProfile = trim((string) env('FIREFOX_PROFILE', ''));
        $this->firefoxBinary = trim((string) env('FIREFOX_BINARY', ''));
        $headlessRaw = trim((string) env('HEADLESS', '0'));
        $this->headless = in_array(strtolower($headlessRaw), ['1', 'true', 'yes'], true);
        $this->timeout = (int) (env('TIMEOUT') ?: 25);
        $this->downloadTimeout = (int) (env('DOWNLOAD_TIMEOUT') ?: 90);
        $this->chartWait = (int) (env('CHART_WAIT') ?: 30);

        if (! is_dir($this->pastaDownload)) {
            mkdir($this->pastaDownload, 0775, true);
        }
    }

    public function run(): int
    {
        $this->validarConfig();
        $this->driver = $this->criarDriver();
        $this->wait = new WebDriverWait($this->driver, $this->timeout);

        try {
            $this->login();
            $this->baixarTodos();

            return 0;
        } catch (Throwable $exc) {
            $this->log('ERRO FATAL:');
            $this->log((string) $exc);
            try {
                $this->driver->takeScreenshot($this->pastaDownload.'/erro_tutory.png');
            } catch (Throwable) {
                try {
                    $this->driver->takeScreenshot('erro_tutory.png');
                } catch (Throwable) {
                    // ignore
                }
            }

            throw $exc;
        } finally {
            if ($this->driver !== null) {
                $this->driver->quit();
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

    private function pareceBinarioFirefox(string $caminho): bool
    {
        if (! is_file($caminho) || ! is_executable($caminho)) {
            return false;
        }

        $fh = @fopen($caminho, 'rb');
        if ($fh === false) {
            return false;
        }
        $inicio = fread($fh, 128) ?: '';
        fclose($fh);

        if (str_starts_with($inicio, '#!')) {
            return false;
        }

        $nome = strtolower(basename($caminho));

        return str_contains($nome, 'firefox') || in_array($nome, ['firefox', 'firefox-bin', 'firefox-esr'], true);
    }

    private function resolverBinarioFirefox(): ?string
    {
        $candidatos = [];

        if ($this->firefoxBinary !== '') {
            $candidatos[] = $this->expandHome($this->firefoxBinary);
        }

        $candidatos = array_merge($candidatos, [
            '/usr/lib/firefox/firefox',
            '/usr/lib/firefox-esr/firefox-esr',
            '/snap/firefox/current/usr/lib/firefox/firefox',
            '/opt/firefox/firefox',
            rtrim((string) getenv('HOME'), '/').'/firefox/firefox',
            '/Applications/Firefox.app/Contents/MacOS/firefox',
        ]);

        $which = trim((string) shell_exec('command -v firefox 2>/dev/null || command -v firefox-esr 2>/dev/null'));
        if ($which !== '') {
            $candidatos[] = realpath($which) ?: $which;
        }

        $vistos = [];
        foreach ($candidatos as $cand) {
            $chave = is_file($cand) ? (realpath($cand) ?: $cand) : $cand;
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            if ($this->pareceBinarioFirefox($cand)) {
                return realpath($cand) ?: $cand;
            }
        }

        if ($this->firefoxBinary !== '') {
            $this->log(
                "AVISO: FIREFOX_BINARY='{$this->firefoxBinary}' não é um executável ".
                'Firefox válido (wrappers como /usr/bin/firefox do snap não servem). '.
                'Tentando deixar o geckodriver achar o padrão...'
            );
        }

        return null;
    }

    private function expandHome(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return rtrim((string) getenv('HOME'), '/').substr($path, 1);
        }

        return $path;
    }

    private function criarDriver(): RemoteWebDriver
    {
        $pasta = realpath($this->pastaDownload) ?: $this->pastaDownload;
        $options = new FirefoxOptions;

        $binario = $this->resolverBinarioFirefox();
        if ($binario !== null) {
            $options->setOption('binary', $binario);
            $this->log("Firefox binary: {$binario}");
        } else {
            $this->log(
                'Firefox binary: (padrão do geckodriver). '.
                'Se falhar, defina FIREFOX_BINARY no .env para o executável real.'
            );
        }

        if ($this->firefoxProfile !== '') {
            $perfil = $this->expandHome($this->firefoxProfile);
            $options->addArguments(['-profile', $perfil]);
            $this->log("Firefox profile: {$perfil}");
        }

        if ($this->headless) {
            $options->addArguments(['-headless']);
        }

        $options->setPreference('browser.download.folderList', 2);
        $options->setPreference('browser.download.dir', $pasta);
        $options->setPreference('browser.download.useDownloadDir', true);
        $options->setPreference('browser.download.manager.showWhenStarting', false);
        $options->setPreference('browser.download.alwaysOpenPanel', false);
        $options->setPreference('browser.download.always_ask_before_handling_new_types', false);
        $options->setPreference('browser.download.improvements_to_download_panel', false);
        $options->setPreference('browser.download.viewableInternally.enabledTypes', '');
        $options->setPreference('pdfjs.disabled', true);
        $options->setPreference(
            'browser.helperApps.neverAsk.saveToDisk',
            'application/pdf,application/x-pdf,application/octet-stream,binary/octet-stream'
        );
        $options->setPreference('browser.helperApps.alwaysAsk.force', false);
        $options->setPreference('browser.download.forbid_open_with', true);

        $capabilities = DesiredCapabilities::firefox();
        $capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

        try {
            $driver = FirefoxDriver::start($capabilities);
        } catch (Throwable $exc) {
            $msg = (string) $exc;
            if (str_contains($msg, 'not a Firefox executable') || str_contains(strtolower($msg), 'binary is not')) {
                throw new RuntimeException(
                    "Não achei um Firefox executável válido para o geckodriver.\n".
                    "No .env, aponte FIREFOX_BINARY para o binário real (não o wrapper):\n".
                    "  FIREFOX_BINARY=/usr/lib/firefox/firefox\n".
                    "  # ou snap:\n".
                    "  FIREFOX_BINARY=/snap/firefox/current/usr/lib/firefox/firefox\n".
                    'Erro original: '.$exc->getMessage(),
                    0,
                    $exc
                );
            }
            throw $exc;
        }

        $driver->manage()->timeouts()->pageLoadTimeout(60);
        try {
            $driver->manage()->window()->maximize();
        } catch (Throwable) {
            $driver->manage()->window()->setSize(new WebDriverDimension(1920, 1080));
        }

        $this->log("Firefox iniciado (download em: {$pasta})");

        return $driver;
    }

    private function jsClick(RemoteWebElement $elemento): void
    {
        $this->driver->executeScript('arguments[0].click();', [$elemento]);
    }

    private function fecharAbasExtras(string $abaPrincipal): void
    {
        foreach ($this->driver->getWindowHandles() as $handle) {
            if ($handle !== $abaPrincipal) {
                $this->driver->switchTo()->window($handle);
                $this->driver->close();
            }
        }
        $this->driver->switchTo()->window($abaPrincipal);
    }

    private function limparOverlays(): void
    {
        $this->driver->executeScript(<<<'JS'
            document.querySelectorAll('.dropdown-menu.show').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.dropdown.show, .btn-group.show').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal.show').forEach(el => {
                el.classList.remove('show');
                el.style.display = 'none';
            });
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.swal-overlay, .swal-modal').forEach(el => el.remove());
        JS);

        try {
            $this->driver->findElement(WebDriverBy::tagName('body'))->sendKeys(WebDriverKeys::ESCAPE);
        } catch (Throwable) {
            // ignore
        }
        usleep(200_000);
    }

    private function elementoVisivel(RemoteWebElement $el): bool
    {
        try {
            if (! $el->isDisplayed()) {
                return false;
            }
            $size = $el->getSize();

            return $size->getHeight() > 0 && $size->getWidth() > 0;
        } catch (StaleElementReferenceException) {
            return false;
        }
    }

    private function esperarLinkRelatorioVisivel(RemoteWebElement $card, ?int $timeout = null): RemoteWebElement
    {
        $timeout ??= $this->timeout;
        $fim = microtime(true) + $timeout;
        $ultimoErro = 'link não apareceu';

        while (microtime(true) < $fim) {
            $candidatos = [];

            foreach ($this->driver->findElements(WebDriverBy::cssSelector(
                '.dropdown-menu.show a.btn-generate-report, '.
                '.dropdown-menu.show a[class*="btn-generate-report"]'
            )) as $el) {
                $candidatos[] = $el;
            }

            foreach ($card->findElements(WebDriverBy::cssSelector(
                '.dropdown-menu a.btn-generate-report, a.btn-generate-report, '.
                '.pesquisa-aluno-acoes a'
            )) as $el) {
                $candidatos[] = $el;
            }

            foreach ($this->driver->findElements(WebDriverBy::xpath(
                "//a[contains(normalize-space(.),'Relatório do Coach') or ".
                "contains(normalize-space(.),'Relatorio do Coach')]"
            )) as $el) {
                $candidatos[] = $el;
            }

            $vistos = [];
            foreach ($candidatos as $el) {
                try {
                    $idEl = $el->getID();
                    if (isset($vistos[$idEl])) {
                        continue;
                    }
                    $vistos[$idEl] = true;

                    $texto = strtolower(trim($el->getText() ?: (string) $el->getAttribute('textContent')));
                    if (! str_contains($texto, 'relat') && ! str_contains($texto, 'coach')) {
                        $classes = (string) $el->getAttribute('class');
                        if (! str_contains($classes, 'btn-generate-report')) {
                            continue;
                        }
                    }
                    if (! $this->elementoVisivel($el)) {
                        continue;
                    }

                    return $el;
                } catch (StaleElementReferenceException) {
                    $ultimoErro = 'elemento stale';
                    continue;
                }
            }

            usleep(250_000);
        }

        throw new TimeoutException(
            "Relatório do Coach visível não encontrado em {$timeout}s ({$ultimoErro})"
        );
    }

    private function login(): void
    {
        $this->log('Abrindo página de login...');
        $this->driver->get($this->urlLogin);

        $account = $this->wait->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('account'))
        );
        $password = $this->wait->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('password'))
        );

        $account->clear();
        $account->sendKeys($this->email);
        $password->clear();
        $password->sendKeys($this->senha);

        $botao = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('input.login-submit'))
        );
        $this->jsClick($botao);
        $this->wait->until(function (RemoteWebDriver $d): bool {
            return ! str_contains($d->getCurrentURL(), '/login');
        });
        $this->log('Login realizado');
    }

    private function filtrarAlunosAtivos(): void
    {
        $this->log("Filtrando alunos com status 'ativos'...");
        $selectEl = $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('status'))
        );
        (new WebDriverSelect($selectEl))->selectByValue('ativos');
        $this->driver->executeScript(<<<'JS'
            arguments[0].value = 'ativos';
            arguments[0].dispatchEvent(new Event('change', {bubbles: true}));
            arguments[0].dispatchEvent(new Event('input', {bubbles: true}));
        JS, [$selectEl]);

        $buscar = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector(
                "input[type='submit'][value='Buscar'], button[type='submit'][value='Buscar']"
            ))
        );
        try {
            $buscar->click();
        } catch (Throwable) {
            $this->jsClick($buscar);
        }
        $this->log('Clicou em Buscar');

        usleep(800_000);
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.pesquisa-aluno-container'))
        );
        $this->log('Filtro de alunos ativos aplicado');
    }

    private function abrirPesquisaAlunos(): void
    {
        $this->log('Abrindo pesquisa de alunos...');
        $this->driver->get(self::URL_CONSULTA);
        $this->wait->until(fn (RemoteWebDriver $d): bool => str_contains($d->getCurrentURL(), '/alunos/consulta'));
        $this->wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('status')));
        $this->filtrarAlunosAtivos();
        $this->log('Pesquisa de alunos aberta');
    }

    /**
     * @return list<array{index: int, nome: string}>
     */
    private function listarAlunosVisiveis(): array
    {
        $cards = $this->driver->findElements(WebDriverBy::cssSelector('.pesquisa-aluno-container'));
        $alunos = [];
        foreach ($cards as $i => $card) {
            try {
                $nomeEl = $card->findElements(WebDriverBy::cssSelector('.pesquisa-aluno-nome'));
                $nome = $nomeEl !== [] ? trim($nomeEl[0]->getText()) : 'aluno_'.($i + 1);
            } catch (StaleElementReferenceException) {
                $nome = 'aluno_'.($i + 1);
            }
            $alunos[] = ['index' => $i, 'nome' => $nome !== '' ? $nome : 'aluno_'.($i + 1)];
        }

        return $alunos;
    }

    private function irParaProximaPagina(): bool
    {
        $primeiro = $this->driver->findElements(
            WebDriverBy::cssSelector('.pesquisa-aluno-container .pesquisa-aluno-nome')
        );
        $textoAntes = $primeiro !== [] ? $primeiro[0]->getText() : '';

        $xpaths = [
            "//li[contains(@class,'page-item') and not(contains(@class,'disabled'))]/a[@rel='next']",
            "//li[contains(@class,'page-item') and not(contains(@class,'disabled'))]".
            "/a[contains(@aria-label,'Next') or contains(@aria-label,'Próximo') or contains(@aria-label,'Proximo')]",
            "//a[contains(@class,'page-link') and (normalize-space()='›' or normalize-space()='»' or normalize-space()='>')]",
            "//a[contains(translate(normalize-space(.),'PRÓXIMOPROXIMO','proximoproximo'),'proximo')]",
        ];

        foreach ($xpaths as $xpath) {
            $links = $this->driver->findElements(WebDriverBy::xpath($xpath));
            foreach ($links as $link) {
                try {
                    $parent = $link->findElement(WebDriverBy::xpath('..'));
                    if (str_contains((string) $parent->getAttribute('class'), 'disabled')) {
                        continue;
                    }
                    $this->jsClick($link);
                    usleep(1_200_000);
                    $this->wait->until(
                        WebDriverExpectedCondition::presenceOfElementLocated(
                            WebDriverBy::cssSelector('.pesquisa-aluno-container')
                        )
                    );
                    $depois = $this->driver->findElements(
                        WebDriverBy::cssSelector('.pesquisa-aluno-container .pesquisa-aluno-nome')
                    );
                    $textoDepois = $depois !== [] ? $depois[0]->getText() : '';
                    if ($textoDepois !== '' && $textoDepois !== $textoAntes) {
                        $this->log('Próxima página de alunos carregada');

                        return true;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function coletarTodosAlunos(): array
    {
        $this->abrirPesquisaAlunos();
        $nomes = [];
        $pagina = 1;

        while (true) {
            $paginaAtual = $this->listarAlunosVisiveis();
            $this->log("Coletando página {$pagina}: ".count($paginaAtual).' aluno(s)');
            foreach ($paginaAtual as $aluno) {
                if (! in_array($aluno['nome'], $nomes, true)) {
                    $nomes[] = $aluno['nome'];
                }
            }
            if (! $this->irParaProximaPagina()) {
                break;
            }
            $pagina++;
        }

        $this->log('Total de alunos encontrados: '.count($nomes));

        return $nomes;
    }

    private function encontrarAlunoPorTrecho(string $trecho): ?string
    {
        $alvo = mb_strtolower(trim($trecho));
        $this->abrirPesquisaAlunos();
        $pagina = 1;

        while (true) {
            $paginaAtual = $this->listarAlunosVisiveis();
            $this->log("Buscando '{$trecho}' na página {$pagina}: ".count($paginaAtual).' aluno(s)');
            foreach ($paginaAtual as $aluno) {
                if (str_contains(mb_strtolower($aluno['nome']), $alvo)) {
                    return $aluno['nome'];
                }
            }
            if (! $this->irParaProximaPagina()) {
                break;
            }
            $pagina++;
        }

        return null;
    }

    private function localizarCardPorNome(string $nome): RemoteWebElement
    {
        $this->limparOverlays();
        $this->abrirPesquisaAlunos();
        $this->limparOverlays();

        while (true) {
            $cards = $this->driver->findElements(WebDriverBy::cssSelector('.pesquisa-aluno-container'));
            foreach ($cards as $card) {
                try {
                    $nomeEl = $card->findElements(WebDriverBy::cssSelector('.pesquisa-aluno-nome'));
                    $atual = $nomeEl !== [] ? trim($nomeEl[0]->getText()) : '';
                } catch (StaleElementReferenceException) {
                    continue;
                }
                if ($atual === $nome) {
                    return $card;
                }
            }
            if (! $this->irParaProximaPagina()) {
                break;
            }
        }

        throw new RuntimeException("Aluno não encontrado na lista: {$nome}");
    }

    private function abrirRelatorioCoachDoCard(RemoteWebElement $card, string $nome): void
    {
        $this->log("[{$nome}] Abrindo opções...");
        $this->limparOverlays();
        $this->driver->executeScript("arguments[0].scrollIntoView({block:'center'});", [$card]);
        usleep(400_000);

        $botaoOpcoes = null;
        foreach ([
            '.pesquisa-aluno-acoes button.dropdown-toggle-split',
            '.pesquisa-aluno-acoes .dropdown-toggle-split',
            'button.dropdown-toggle-split',
            '.dropdown-toggle-split',
        ] as $seletor) {
            $achados = $card->findElements(WebDriverBy::cssSelector($seletor));
            if ($achados !== []) {
                $botaoOpcoes = $achados[0];
                break;
            }
        }
        if ($botaoOpcoes === null) {
            $achados = $card->findElements(
                WebDriverBy::cssSelector('.pesquisa-aluno-acoes button, .dropdown button')
            );
            if ($achados !== []) {
                $botaoOpcoes = $achados[0];
            }
        }
        if ($botaoOpcoes === null) {
            throw new RuntimeException("[{$nome}] Botão de opções não encontrado no card.");
        }

        try {
            $this->wait->until(static function () use ($botaoOpcoes): bool {
                return $botaoOpcoes->isDisplayed() && $botaoOpcoes->isEnabled();
            });
            $botaoOpcoes->click();
        } catch (Throwable) {
            $this->jsClick($botaoOpcoes);
        }

        usleep(350_000);
        $menusAbertos = $this->driver->findElements(WebDriverBy::cssSelector('.dropdown-menu.show'));
        if ($menusAbertos === []) {
            $this->log("[{$nome}] Menu não abriu no 1º clique; tentando de novo...");
            $this->limparOverlays();
            usleep(200_000);
            try {
                $botaoOpcoes->click();
            } catch (Throwable) {
                $this->jsClick($botaoOpcoes);
            }
            usleep(350_000);
        }

        $relatorio = $this->esperarLinkRelatorioVisivel($card);
        try {
            $relatorio->click();
        } catch (Throwable) {
            $this->jsClick($relatorio);
        }
        $this->log("[{$nome}] Relatório do Coach aberto");

        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector(
                "button.btn-selector[data-value='questoes'], #relDataIni"
            ))
        );
    }

    private function configurarFiltrosRelatorio(string $nome): void
    {
        $this->log("[{$nome}] Configurando filtros...");

        $questoes = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector("button.btn-selector[data-value='questoes']")
            )
        );
        $this->jsClick($questoes);

        $mes = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector("button.btn-selector[data-value='mes']")
            )
        );
        $this->jsClick($mes);

        $hoje = new \DateTimeImmutable('now');
        if ($this->periodo === '1') {
            $dataInicio = $hoje->format('Y-m-01');
            $dataFim = $hoje->format('Y-m-15');
        } else {
            $ultimoDia = (int) $hoje->format('t');
            $dataInicio = $hoje->format('Y-m-16');
            $dataFim = $hoje->format('Y-m-').str_pad((string) $ultimoDia, 2, '0', STR_PAD_LEFT);
        }
        $this->log("[{$nome}] Datas: {$dataInicio} → {$dataFim}");

        $campoInicio = $this->wait->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('relDataIni'))
        );
        $campoFim = $this->wait->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('relDataFim'))
        );

        $this->driver->executeScript(<<<'JS'
            arguments[0].value = arguments[1];
            arguments[0].dispatchEvent(new Event('change', {bubbles:true}));
        JS, [$campoInicio, $dataInicio]);
        $this->driver->executeScript(<<<'JS'
            arguments[0].value = arguments[1];
            arguments[0].dispatchEvent(new Event('change', {bubbles:true}));
        JS, [$campoFim, $dataFim]);

        $gerar = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector('a.btn-generate-my-report')
            )
        );
        $this->jsClick($gerar);
        $this->log("[{$nome}] Relatório solicitado");
    }

    /**
     * @param  list<string>  $pastas
     * @return set<string>
     */
    private function listarArquivosDownload(array $pastas): array
    {
        $arquivos = [];
        foreach ($pastas as $pasta) {
            if (! is_dir($pasta)) {
                continue;
            }
            foreach (glob(rtrim($pasta, '/').'/*') ?: [] as $p) {
                if (is_file($p)) {
                    $arquivos[$p] = true;
                }
            }
        }

        return $arquivos;
    }

    /**
     * @param  set<string>  $antes
     * @param  list<string>|null  $pastas
     */
    private function aguardarNovoDownload(array $antes, ?array $pastas = null, ?int $timeout = null): ?string
    {
        $timeout ??= $this->downloadTimeout;
        $dirs = $pastas ?? [$this->pastaDownload];
        $downloadsPadrao = rtrim((string) getenv('HOME'), '/').'/Downloads';
        $resolved = array_map(static fn (string $d): string => realpath($d) ?: $d, $dirs);
        if (! in_array(realpath($downloadsPadrao) ?: $downloadsPadrao, $resolved, true)) {
            $dirs[] = $downloadsPadrao;
        }

        $fim = microtime(true) + $timeout;
        while (microtime(true) < $fim) {
            $atuais = $this->listarArquivosDownload($dirs);
            $baixando = [];
            foreach (array_keys($atuais) as $p) {
                if (
                    str_ends_with($p, '.part')
                    || str_ends_with($p, '.crdownload')
                    || str_ends_with($p, '.tmp')
                    || str_ends_with($p, '.download')
                ) {
                    $baixando[$p] = true;
                }
            }

            $novos = [];
            foreach (array_keys($atuais) as $p) {
                if (isset($antes[$p]) || isset($baixando[$p])) {
                    continue;
                }
                $name = basename($p);
                if (str_starts_with($name, '.')) {
                    continue;
                }
                $suffix = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                if (
                    $suffix === 'pdf'
                    || $suffix === ''
                    || str_contains(strtolower($name), 'relat')
                ) {
                    $novos[] = $p;
                }
            }

            if ($novos !== [] && $baixando === []) {
                usort($novos, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

                return $novos[0];
            }
            usleep(500_000);
        }

        return null;
    }

    private function renomearDownload(string $caminho, string $nomeAluno): string
    {
        $origem = $caminho;
        $seguro = preg_replace('/[^A-Za-z0-9 ._\\-]/u', '_', $nomeAluno) ?? 'aluno';
        $seguro = trim(str_replace(' ', '_', $seguro)) ?: 'aluno';
        $mes = date('Y-m');
        $ext = pathinfo($origem, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? '.'.$ext : '.pdf';
        $dir = dirname($origem);
        $destino = $dir.'/'.$seguro.'_'.$mes.$ext;
        $contador = 1;
        while (file_exists($destino)) {
            $destino = $dir.'/'.$seguro.'_'.$mes.'_'.$contador.$ext;
            $contador++;
        }
        rename($origem, $destino);

        return $destino;
    }

    private function prepararGraficosParaPdf(string $nome): int
    {
        $this->log("[{$nome}] Aguardando #chart_questoes_dia para o PDF...");

        $chartWait = new WebDriverWait($this->driver, $this->chartWait);
        $chartWait->until(function (RemoteWebDriver $d): bool {
            return (bool) $d->executeScript(<<<'JS'
                const target = document.getElementById('chart_questoes_dia');
                if (!target) return false;
                const canvas = target.tagName === 'CANVAS'
                  ? target
                  : target.querySelector('canvas');
                return !!(canvas && canvas.width > 10 && canvas.height > 10);
            JS);
        });
        sleep(5);

        $convertido = $this->driver->executeAsyncScript(<<<'JS'
            const done = arguments[0];
            (async () => {
              document.querySelectorAll('.tutory-chart-questoes-dia-overlay')
                .forEach(el => el.remove());

              async function imageReady(url) {
                const img = new Image();
                img.src = url;
                await new Promise((resolve, reject) => {
                  img.onload = resolve;
                  img.onerror = reject;
                });
                return img;
              }

              try {
                const target = document.getElementById('chart_questoes_dia');
                if (!target) return done(0);
                const canvas = target.tagName === 'CANVAS'
                  ? target
                  : target.querySelector('canvas');
                if (!canvas) return done(0);

                try {
                  const chart = window.Chart && Chart.getChart
                    ? Chart.getChart(canvas)
                    : null;
                  if (chart) {
                    if (chart.options) chart.options.animation = false;
                    if (typeof chart.stop === 'function') chart.stop();
                    if (typeof chart.update === 'function') chart.update('none');
                    if (typeof chart.draw === 'function') chart.draw();
                  }
                } catch (e) {}
                await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

                const url = canvas.toDataURL('image/png', 1.0);
                const check = await imageReady(url);
                if (!check.naturalWidth) return done(0);

                const parent = canvas.parentElement;
                if (!parent) return done(0);
                const originalPosition = getComputedStyle(parent).position;
                if (originalPosition === 'static') parent.style.position = 'relative';

                const overlay = document.createElement('img');
                overlay.className = 'tutory-chart-questoes-dia-overlay';
                overlay.src = url;
                overlay.alt = '';
                overlay.setAttribute('aria-hidden', 'true');
                overlay.style.cssText = [
                  'position:absolute',
                  'left:' + canvas.offsetLeft + 'px',
                  'top:' + canvas.offsetTop + 'px',
                  'width:' + canvas.offsetWidth + 'px',
                  'height:' + canvas.offsetHeight + 'px',
                  'z-index:10',
                  'pointer-events:none',
                  'display:block'
                ].join(';');
                parent.appendChild(overlay);
                done(1);
              } catch (e) {
                done(0);
              }
            })().catch(() => done(0));
        JS);

        usleep(800_000);
        $this->log("[{$nome}] PNG sobreposto a #chart_questoes_dia: ".(int) $convertido);

        return (int) $convertido;
    }

    private function acessarBaixarRelatorio(string $abaPrincipal, string $nome): ?string
    {
        $this->log("[{$nome}] Aguardando popup do relatório...");
        $pastasMonitor = [$this->pastaDownload, rtrim((string) getenv('HOME'), '/').'/Downloads'];
        $antes = $this->listarArquivosDownload($pastasMonitor);

        $acessar = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector('button.swal-button--confirm')
            )
        );
        $this->jsClick($acessar);
        $this->log("[{$nome}] Clicou em Acessar Relatório");

        $this->wait->until(fn (RemoteWebDriver $d): bool => count($d->getWindowHandles()) > 1);
        foreach ($this->driver->getWindowHandles() as $aba) {
            if ($aba !== $abaPrincipal) {
                $this->driver->switchTo()->window($aba);
                break;
            }
        }

        $this->log("[{$nome}] Aba do relatório: ".$this->driver->getCurrentURL());
        $baixar = $this->wait->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('btn_save'))
        );
        $this->prepararGraficosParaPdf($nome);

        try {
            $baixar->click();
        } catch (Throwable) {
            $this->jsClick($baixar);
        }
        $this->log("[{$nome}] Download iniciado");

        $arquivo = $this->aguardarNovoDownload($antes, $pastasMonitor);
        $final = null;
        if ($arquivo !== null) {
            $destinoDir = realpath($this->pastaDownload) ?: $this->pastaDownload;
            $origemDir = realpath(dirname($arquivo)) ?: dirname($arquivo);
            if ($origemDir !== $destinoDir) {
                if (! is_dir($destinoDir)) {
                    mkdir($destinoDir, 0775, true);
                }
                $movido = $destinoDir.'/'.basename($arquivo);
                $contador = 1;
                while (file_exists($movido)) {
                    $info = pathinfo($arquivo);
                    $movido = $destinoDir.'/'.$info['filename'].'_'.$contador.
                        (isset($info['extension']) ? '.'.$info['extension'] : '');
                    $contador++;
                }
                rename($arquivo, $movido);
                $arquivo = $movido;
                $this->log("[{$nome}] Movido de Downloads → {$arquivo}");
            }
            $final = $this->renomearDownload($arquivo, $nome);
            $this->log("[{$nome}] Arquivo salvo: {$final}");
        } else {
            $this->log("[{$nome}] AVISO: não detectei arquivo novo em {$this->pastaDownload}");
            try {
                $amostra = glob(rtrim($this->pastaDownload, '/').'/*') ?: [];
                usort($amostra, static fn ($a, $b) => filemtime($a) <=> filemtime($b));
                $ultimos = array_slice($amostra, -5);
                $this->log("[{$nome}] Últimos arquivos na pasta: ".json_encode(
                    array_map('basename', $ultimos),
                    JSON_UNESCAPED_UNICODE
                ));
            } catch (Throwable) {
                // ignore
            }
        }

        $this->fecharAbasExtras($abaPrincipal);

        return $final;
    }

    private function processarAluno(string $abaPrincipal, string $nome): ?string
    {
        try {
            $this->fecharAbasExtras($abaPrincipal);
            $this->limparOverlays();
            $card = $this->localizarCardPorNome($nome);
            $this->abrirRelatorioCoachDoCard($card, $nome);
            $this->configurarFiltrosRelatorio($nome);
            $arquivo = $this->acessarBaixarRelatorio($abaPrincipal, $nome);
            $this->limparOverlays();

            return $arquivo;
        } catch (
            TimeoutException|
            ElementClickInterceptedException|
            RuntimeException|
            StaleElementReferenceException|
            NoSuchElementException $exc
        ) {
            $this->log("[{$nome}] ERRO: ".$exc->getMessage());
            try {
                $shotNome = preg_replace('/\s+/', '_', $nome) ?? 'aluno';
                $shot = $this->pastaDownload.'/erro_'.substr($shotNome, 0, 40).'.png';
                $this->driver->takeScreenshot($shot);
                $this->log("[{$nome}] Screenshot: {$shot}");
            } catch (Throwable) {
                // ignore
            }
            try {
                $this->limparOverlays();
                $this->fecharAbasExtras($abaPrincipal);
            } catch (Throwable) {
                // ignore
            }

            return null;
        }
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
        $caminho = $this->pastaDownload.'/log_download_'.$inicio->format('Ymd_His').'.txt';
        $linhas = [
            'Relatórios Tutory - resumo da execução',
            'Início: '.$inicio->format('d/m/Y H:i:s'),
            'Fim:    '.$fim->format('d/m/Y H:i:s'),
            'Duração: '.$this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()),
            "Alunos processados: {$totalAlunos}",
            'PDFs baixados: '.count($pdfs),
            'Falhas finais: '.count($falhas),
            "Pasta: {$this->pastaDownload}",
            '',
            'Arquivos:',
        ];
        if ($pdfs !== []) {
            foreach ($pdfs as $p) {
                $linhas[] = '- '.basename($p);
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

        file_put_contents($caminho, implode("\n", $linhas)."\n");

        return $caminho;
    }

    private function baixarTodos(): void
    {
        $inicio = new \DateTimeImmutable('now');
        $this->log('Processo iniciado em: '.$inicio->format('d/m/Y H:i:s'));

        $abaPrincipal = $this->driver->getWindowHandle();

        if ($this->teste) {
            $nomeTeste = $this->encontrarAlunoPorTrecho(self::ALUNA_TESTE);
            $nomes = $nomeTeste !== null ? [$nomeTeste] : [];
            if ($nomes !== []) {
                $this->log(
                    "Modo --teste: processando apenas {$nomes[0]} ".
                    'para validar o PDF com gráficos'
                );
            } else {
                $this->log("Modo --teste: aluna '".self::ALUNA_TESTE."' não encontrada na lista.");
            }
        } else {
            $nomes = $this->coletarTodosAlunos();
        }

        if ($nomes === []) {
            $fim = new \DateTimeImmutable('now');
            $this->log('Nenhum aluno encontrado em /alunos/consulta.');
            $log = $this->gravarLogResumo($inicio, $fim, 0, [], [], []);
            $this->log("Log salvo em: {$log}");

            return;
        }

        /** @var array<string, array{nome: string, sucesso: bool, arquivo: ?string, tentativas: int}> $resultados */
        $resultados = [];
        foreach ($nomes as $nome) {
            $resultados[$nome] = [
                'nome' => $nome,
                'sucesso' => false,
                'arquivo' => null,
                'tentativas' => 0,
            ];
        }

        $processarLote = function (array $lista, int $rodada) use (&$resultados, $abaPrincipal): void {
            $total = count($lista);
            foreach ($lista as $i => $nome) {
                $resultados[$nome]['tentativas']++;
                $tentativa = $resultados[$nome]['tentativas'];
                $n = $i + 1;
                $this->log(str_repeat('=', 50));
                $this->log(
                    "[rodada {$rodada}] Aluno {$n}/{$total}: {$nome} ".
                    "(tentativa {$tentativa}/".self::MAX_TENTATIVAS.')'
                );
                $arquivo = $this->processarAluno($abaPrincipal, $nome);
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
                static fn (string $n) => ! $resultados[$n]['sucesso']
            ));
            if ($pendentes === []) {
                $this->log(str_repeat('=', 50));
                $this->log('Nenhuma falha restante — sem reprocessamento.');
                break;
            }
            $this->log(str_repeat('=', 50));
            $this->log(
                'Reprocessando '.count($pendentes).' aluno(s) com erro '.
                "(rodada {$rodada}/".self::MAX_TENTATIVAS.')...'
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
        $listaResultados = array_map(static fn (string $n) => $resultados[$n], $nomes);

        $fim = new \DateTimeImmutable('now');
        $log = $this->gravarLogResumo($inicio, $fim, count($nomes), $pdfs, $falhas, $listaResultados);

        $this->log(str_repeat('=', 50));
        $this->log('Início: '.$inicio->format('d/m/Y H:i:s'));
        $this->log('Fim:    '.$fim->format('d/m/Y H:i:s'));
        $this->log('Duração: '.$this->formatarDuracao($fim->getTimestamp() - $inicio->getTimestamp()));
        $this->log('PDFs baixados: '.count($pdfs));
        $this->log('Falhas finais: '.count($falhas));
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
