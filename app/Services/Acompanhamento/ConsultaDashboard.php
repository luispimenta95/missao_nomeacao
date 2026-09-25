<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\FiltroSituacaoAcompanhamento;
use App\Enums\FocoAcompanhamento;
use Illuminate\Http\Request;

final class ConsultaDashboard
{
    public function __construct(
        public FiltroSituacaoAcompanhamento $situacao,
        public ?FiltroParametroAcompanhamento $parametro,
        public ?FocoAcompanhamento $foco,
        public ?AcaoAcompanhamento $acao,
        public string $busca,
        public ?int $alunoId,
        public int $pagina,
        public ?string $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $aluno = $request->query('aluno');

        return new self(
            situacao: FiltroSituacaoAcompanhamento::tryFrom((string) $request->query('situacao', 'pendentes'))
                ?? FiltroSituacaoAcompanhamento::Pendentes,
            parametro: FiltroParametroAcompanhamento::tryFrom((string) $request->query('parametro', '')),
            foco: FocoAcompanhamento::tryFrom((string) $request->query('foco', '')),
            acao: AcaoAcompanhamento::tryFrom((string) $request->query('acao', '')),
            busca: trim((string) $request->query('busca', '')),
            alunoId: is_numeric($aluno) ? (int) $aluno : null,
            pagina: max(1, (int) $request->query('page', 1)),
            status: in_array($request->query('status'), ['ativos', 'inativos'], true)
                ? (string) $request->query('status')
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public function parametros(array $override = []): array
    {
        $dados = [
            'situacao' => $this->situacao->value,
            'parametro' => $this->parametro?->value,
            'foco' => $this->foco?->value,
            'acao' => $this->acao?->value,
            'busca' => $this->busca !== '' ? $this->busca : null,
            'aluno' => $this->alunoId,
            'page' => $this->pagina > 1 ? $this->pagina : null,
            'status' => $this->status,
        ];

        foreach ($override as $chave => $valor) {
            $dados[$chave] = $valor;
        }

        if (($dados['page'] ?? null) !== null && (int) $dados['page'] < 2) {
            $dados['page'] = null;
        }

        return array_filter(
            $dados,
            static fn ($valor) => $valor !== null && $valor !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $override
     */
    public function url(array $override = []): string
    {
        return route('admin.dashboard', $this->parametros($override));
    }

    public function temFiltro(): bool
    {
        return $this->situacao !== FiltroSituacaoAcompanhamento::Pendentes
            || $this->parametro !== null
            || $this->foco !== null
            || $this->acao !== null
            || $this->busca !== ''
            || $this->status !== null;
    }
}
