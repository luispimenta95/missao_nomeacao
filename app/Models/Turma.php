<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Turma extends Model
{
    use HasFactory;

    public const GRUPO_BASE_GERAL = 'base_geral';
    public const GRUPO_BASE_CARREIRA = 'base_por_carreira';
    public const GRUPO_TURMA_DIRECIONADA = 'turma_direcionada';

    public const ACAO_CHECKOUT = 'checkout';
    public const ACAO_WHATSAPP = 'whatsapp';
    public const ACAO_LISTA = 'lista_formulario';

    public const ESTAGIO_EM_BREVE = 'em_breve';
    public const ESTAGIO_LISTA_INTERESSE = 'lista_interesse';
    public const ESTAGIO_INSCRICOES_ABERTAS = 'inscricoes_abertas';

    public const GRUPOS_EXIBICAO = [
        self::GRUPO_BASE_GERAL => 'Base geral',
        self::GRUPO_BASE_CARREIRA => 'Base por carreira',
        self::GRUPO_TURMA_DIRECIONADA => 'Turma direcionada',
    ];

    public const CATEGORIAS_NAVEGACAO = [
        'Policiais' => 'Policiais',
        'Administrativas' => 'Administrativas',
        'Tribunais' => 'Tribunais',
        'Legislativas' => 'Legislativas',
        'Controle' => 'Controle',
        'Inteligência' => 'Inteligência',
        'Núcleo duro' => 'Núcleo duro',
    ];

    public const ORGAOS = [
        'PRF', 'PF', 'PMDF', 'PCDF', 'PMSC', 'ABIN', 'TRF', 'TRE', 'MPU', 'TCU',
        'Câmara', 'Senado', 'ALEGO', 'PMGO',
    ];

    public const CARGOS = [
        'Policial', 'Praça', 'Agente', 'Escrivão', 'Agente de Custódia',
        'Agente Administrativo', 'Analista', 'Analista Judiciário', 'Técnico',
    ];

    public const MOMENTOS_CONCURSO = [
        'sem_movimentacao' => 'Sem movimentação',
        'previsto' => 'Previsto',
        'edital_iminente' => 'Edital iminente',
        'edital_publicado' => 'Edital publicado',
        'inscricoes_abertas' => 'Inscrições do concurso abertas',
        'prova_proxima' => 'Prova próxima',
    ];

    public const MOMENTOS_CONCURSO_PUBLICO = [
        'sem_movimentacao' => 'SEM MOVIMENTAÇÃO',
        'previsto' => 'CONCURSO PREVISTO',
        'edital_iminente' => 'EDITAL IMINENTE',
        'edital_publicado' => 'EDITAL PUBLICADO',
        'inscricoes_abertas' => 'INSCRIÇÕES ABERTAS',
        'prova_proxima' => 'PROVA PRÓXIMA',
    ];

    public const ACOES_PRINCIPAIS = [
        self::ACAO_CHECKOUT => 'Checkout',
        self::ACAO_WHATSAPP => 'WhatsApp',
        self::ACAO_LISTA => 'Lista-formulário',
    ];

    public const SECOES_PAGINA = [
        'Ainda está formando sua base?',
        'Já tem um concurso ou carreira como alvo?',
    ];

    public const ESTAGIOS = [
        self::ESTAGIO_EM_BREVE => 'Em breve',
        self::ESTAGIO_LISTA_INTERESSE => 'Lista de interesse',
        self::ESTAGIO_INSCRICOES_ABERTAS => 'Inscrições abertas',
    ];

    protected $attributes = [
            'status' => 'aberta',
            'checkout_url' => '',
        'ativo' => true,
        'exibir_no_site' => true,
        'plano_pronto_tutory' => false,
        'aceitar_novos_alunos' => true,
        'grupo_exibicao' => self::GRUPO_TURMA_DIRECIONADA,
        'exibir_momento_concurso' => false,
        'acao_principal' => self::ACAO_CHECKOUT,
        'destacar_turmas_abertas' => false,
        'exibir_na_mentoria' => false,
        'ordem_exibicao' => 0,
    ];

    protected $fillable = [
        'title',
        'slug',
        'nome_publico',
        'description',
        'logo_path',
        'checkout_url',
        'whatsapp_url',
        'interesse_url',
        'start_date',
        'available_slots',
        'status',
        'ativo',
        'exibir_no_site',
        'plano_pronto_tutory',
        'aceitar_novos_alunos',
        'grupo_exibicao',
        'categoria_navegacao',
        'orgao',
        'cargo',
        'termos_busca',
        'momento_concurso',
        'exibir_momento_concurso',
        'acao_principal',
        'popup_opcoes',
        'destacar_turmas_abertas',
        'exibir_na_mentoria',
        'ordem_exibicao',
        'secao_pagina',
        'texto_cta',
    ];

    protected $casts = [
        'start_date' => 'date',
        'ativo' => 'boolean',
        'exibir_no_site' => 'boolean',
        'plano_pronto_tutory' => 'boolean',
        'aceitar_novos_alunos' => 'boolean',
        'exibir_momento_concurso' => 'boolean',
        'destacar_turmas_abertas' => 'boolean',
        'exibir_na_mentoria' => 'boolean',
        'popup_opcoes' => 'array',
        'ordem_exibicao' => 'integer',
        'available_slots' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Turma $turma) {
            $turma->ensureSlug();
            $turma->syncStatusOperacional();
        });
    }

    public function inscricoes()
    {
        return $this->hasMany(Inscricao::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class);
    }

    public function scopePublicasNoSite(Builder $query): Builder
    {
        return $query->where('ativo', true)->where('exibir_no_site', true);
    }

    public function scopeNaMentoria(Builder $query): Builder
    {
        return $query->publicasNoSite()->where('exibir_na_mentoria', true);
    }

    public function scopeNasTurmasAbertas(Builder $query): Builder
    {
        return $query->publicasNoSite();
    }

    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('ordem_exibicao')->orderBy('title');
    }

    public function nomePublicoExibido(): string
    {
        return filled($this->nome_publico) ? $this->nome_publico : (string) $this->title;
    }

    public function secaoPaginaPublica(): string
    {
        if (filled($this->secao_pagina)) {
            return $this->secao_pagina;
        }

        return in_array($this->grupo_exibicao, [self::GRUPO_BASE_GERAL, self::GRUPO_BASE_CARREIRA], true)
            ? 'Ainda está formando sua base?'
            : 'Já tem um concurso ou carreira como alvo?';
    }

    public function estagio(): string
    {
        if (! $this->aceitar_novos_alunos) {
            return self::ESTAGIO_EM_BREVE;
        }

        if ($this->acao_principal === self::ACAO_LISTA) {
            return self::ESTAGIO_LISTA_INTERESSE;
        }

        return self::ESTAGIO_INSCRICOES_ABERTAS;
    }

    public function badgePublico(): ?string
    {
        if ($this->estagio() === self::ESTAGIO_EM_BREVE) {
            return 'EM BREVE';
        }

        return match ($this->estagio()) {
            self::ESTAGIO_LISTA_INTERESSE => 'LISTA DE INTERESSE',
            default => 'INSCRIÇÕES ABERTAS',
        };
    }

    public function textoCtaPublico(): string
    {
        if (filled($this->texto_cta)) {
            return $this->texto_cta;
        }

        return match ($this->estagio()) {
            self::ESTAGIO_EM_BREVE => 'ENTRAR NO GRUPO',
            self::ESTAGIO_LISTA_INTERESSE => 'QUERO SER AVISADO',
            default => 'COMEÇAR AGORA',
        };
    }

    public function linkCta(): ?string
    {
        $checkout = filled($this->checkout_url) ? $this->checkout_url : null;
        $whatsapp = filled($this->whatsapp_url) ? $this->whatsapp_url : null;
        $interesse = filled($this->interesse_url) ? $this->interesse_url : null;

        return match ($this->estagio()) {
            self::ESTAGIO_EM_BREVE => $whatsapp,
            self::ESTAGIO_LISTA_INTERESSE => $interesse ?: $whatsapp,
            default => match ($this->acao_principal) {
                self::ACAO_WHATSAPP => $whatsapp,
                self::ACAO_LISTA => $interesse,
                default => $checkout,
            },
        };
    }

    public function momentoConcursoPublico(): ?string
    {
        if (! $this->exibir_momento_concurso || blank($this->momento_concurso)) {
            return null;
        }

        return self::MOMENTOS_CONCURSO_PUBLICO[$this->momento_concurso] ?? Str::upper(str_replace('_', ' ', $this->momento_concurso));
    }

    public function capaUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return asset('storage/'.$this->logo_path);
    }

    public function popupOpcoesNormalizadas(): array
    {
        $opcoes = $this->popup_opcoes ?? [];

        if (! is_array($opcoes)) {
            return [];
        }

        return collect($opcoes)
            ->map(function ($opcao) {
                $label = trim((string) ($opcao['label'] ?? ''));
                $url = trim((string) ($opcao['url'] ?? ''));

                if ($label === '' || $url === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function aceitaInscricao(): bool
    {
        if (! $this->ativo || ! $this->aceitar_novos_alunos) {
            return false;
        }

        if ($this->available_slots !== null && $this->available_slots <= 0) {
            return false;
        }

        return true;
    }

    protected function ensureSlug(): void
    {
        $base = filled($this->slug)
            ? Str::slug($this->slug)
            : Str::slug((string) $this->title);

        if ($base === '') {
            $base = 'turma';
        }

        $slug = $base;
        $i = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $q) => $q->where('id', '!=', $this->id))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        $this->slug = $slug;
    }

    protected function syncStatusOperacional(): void
    {
        if (! $this->aceitar_novos_alunos) {
            $this->status = 'fechada';

            return;
        }

        if ($this->available_slots !== null && (int) $this->available_slots <= 0) {
            $this->status = 'completa';

            return;
        }

        $this->status = 'aberta';
    }
}
