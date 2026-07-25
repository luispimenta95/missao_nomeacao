<?php

namespace Database\Seeders;

use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParametrosDesempenhoSeeder extends Seeder
{
    public function run(): void
    {
        $eixos = [
            [
                'codigo' => EixoDesempenho::CONSTANCIA,
                'nome' => 'Constância na quinzena',
                'descricao' => 'Avalia dias falhados no período (total de dias − dias estudados).',
                'ordem' => 1,
                'faixas' => [
                    [
                        'codigo' => 'excelente',
                        'nome' => 'Excelente',
                        'valor_min' => 0,
                        'valor_max' => 0,
                        'ordem' => 1,
                        'texto_email' => 'Você estudou em todos os {Y} dias analisados, sem deixar nenhum dia zerado. Sua constância foi excelente — parabéns! Preserve essa dedicação nos próximos períodos; ela é a base do seu projeto de vida.',
                    ],
                    [
                        'codigo' => 'bom',
                        'nome' => 'Bom',
                        'valor_min' => 1,
                        'valor_max' => 3,
                        'ordem' => 2,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dia(s) sem estudar. Sua constância foi boa e a frequência no período ficou satisfatória — ainda assim, dá para deixar ainda mais consistente.',
                    ],
                    [
                        'codigo' => 'brigando',
                        'nome' => 'Brigando com a constância',
                        'valor_min' => 4,
                        'valor_max' => 10,
                        'ordem' => 3,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar. A briga com a constância está forte neste período. No próximo ciclo, priorize manter contato quase diário com o seu compromisso pessoal de estudos.',
                    ],
                    [
                        'codigo' => 'critico',
                        'nome' => 'Crítico',
                        'valor_min' => 11,
                        'valor_max' => null,
                        'ordem' => 4,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar. Sua constância está em nível crítico: foram muitos dias sem contato com os estudos. A prioridade agora é retomar o quanto antes — você tem um projeto de vida para realizar.',
                    ],
                ],
            ],
            [
                'codigo' => EixoDesempenho::VOLUME_QUESTOES,
                'nome' => 'Quantidade total de questões',
                'descricao' => 'Verifica se a amostra de questões é suficiente antes de analisar o percentual de acertos.',
                'ordem' => 2,
                'faixas' => [
                    [
                        'codigo' => 'critico_inconclusivo',
                        'nome' => 'Crítico e inconclusivo',
                        'valor_min' => 0,
                        'valor_max' => 49,
                        'ordem' => 1,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões no período. Esse volume está crítico e ainda é insuficiente para avaliarmos seu percentual de acertos com segurança. Priorize aumentar a quantidade de questões no próximo período.',
                    ],
                    [
                        'codigo' => 'volume_baixo',
                        'nome' => 'Volume baixo',
                        'valor_min' => 50,
                        'valor_max' => 99,
                        'ordem' => 2,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões no período. O volume ainda está abaixo do recomendado e deixa a análise do percentual pouco conclusiva. Busque chegar a pelo menos 100 questões no próximo período para uma leitura mais segura do seu desempenho.',
                    ],
                    [
                        'codigo' => 'volume_suficiente',
                        'nome' => 'Volume suficiente',
                        'valor_min' => 100,
                        'valor_max' => 500,
                        'ordem' => 3,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões no período. Esse volume é suficiente para avaliarmos seu percentual de acertos com mais segurança.',
                    ],
                    [
                        'codigo' => 'volume_alto',
                        'nome' => 'Volume alto',
                        'valor_min' => 501,
                        'valor_max' => null,
                        'ordem' => 4,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões no período. Volume alto de treinamento — dá para avaliar o percentual de acertos com bastante segurança. Excelente empenho!',
                    ],
                ],
            ],
            [
                'codigo' => EixoDesempenho::PERCENTUAL_ACERTOS,
                'nome' => 'Percentual geral de acertos',
                'descricao' => 'Só é aplicado quando o aluno fez 100 questões ou mais no período.',
                'ordem' => 3,
                'faixas' => [
                    [
                        'codigo' => 'critico',
                        'nome' => 'Crítico',
                        'valor_min' => 0,
                        'valor_max' => 60,
                        'ordem' => 1,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Esse resultado foi sinalizado como CRÍTICO e merece uma análise mais próxima dos conteúdos e da forma de revisão.',
                    ],
                    [
                        'codigo' => 'alerta',
                        'nome' => 'Alerta',
                        'valor_min' => 60.01,
                        'valor_max' => 70,
                        'ordem' => 2,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Esse resultado é um PONTO DE ATENÇÃO: já há algum domínio, mas ainda existe uma quantidade relevante de erros que precisa ser compreendida e corrigida.',
                    ],
                    [
                        'codigo' => 'mediano',
                        'nome' => 'Mediano',
                        'valor_min' => 70.01,
                        'valor_max' => 79.99,
                        'ordem' => 3,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Resultado MEDIANO: você já construiu uma base importante, porém ainda há erros que impedem um desempenho mais seguro. Foque nos assuntos que mais derrubam sua taxa.',
                    ],
                    [
                        'codigo' => 'muito_bom',
                        'nome' => 'Muito bom',
                        'valor_min' => 80,
                        'valor_max' => 89.99,
                        'ordem' => 4,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Resultado MUITO BOM — desempenho consistente. Preserve o que está funcionando e observe os assuntos que ainda concentram erros, sem mudar toda a estratégia de uma vez.',
                    ],
                    [
                        'codigo' => 'excelente',
                        'nome' => 'Excelente',
                        'valor_min' => 90,
                        'valor_max' => 100,
                        'ordem' => 5,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Resultado EXCELENTE, com domínio elevado dos conteúdos praticados. O desafio agora é manter esse nível, corrigir erros pontuais e não deixar o bom resultado virar acomodação.',
                    ],
                ],
            ],
            [
                'codigo' => EixoDesempenho::ASSUNTO,
                'nome' => 'Percentual por disciplina/assunto',
                'descricao' => 'Assuntos com desempenho baixo (até 75%) entram em acompanhamento no e-mail.',
                'ordem' => 4,
                'faixas' => [
                    [
                        'codigo' => 'critico',
                        'nome' => 'Crítico',
                        'valor_min' => 0,
                        'valor_max' => 60,
                        'ordem' => 1,
                        'texto_email' => "{LISTA_ASSUNTOS}\n\nEsses resultados foram sinalizados como possíveis gargalos e merecem uma análise mais próxima para definir a correção de rota.",
                    ],
                    [
                        'codigo' => 'abaixo_media',
                        'nome' => 'Abaixo da média',
                        'valor_min' => 60.01,
                        'valor_max' => 75,
                        'ordem' => 2,
                        'texto_email' => "{LISTA_ASSUNTOS}\n\nEsses dados vão ficar em acompanhamento nos próximos relatórios.\nA prioridade agora é reduzir os erros recorrentes e verificar se o percentual evolui nos próximos períodos. Caso o desempenho permaneça nessa faixa ou apresente queda, faremos uma análise mais próxima para definir a correção de rota, ok?",
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($eixos): void {
            foreach ($eixos as $def) {
                $eixo = EixoDesempenho::query()->updateOrCreate(
                    ['codigo' => $def['codigo']],
                    [
                        'nome' => $def['nome'],
                        'descricao' => $def['descricao'],
                        'ordem' => $def['ordem'],
                        'ativo' => true,
                    ]
                );

                foreach ($def['faixas'] as $faixaDef) {
                    FaixaDesempenho::query()->updateOrCreate(
                        [
                            'eixo_desempenho_id' => $eixo->id,
                            'codigo' => $faixaDef['codigo'],
                        ],
                        [
                            'nome' => $faixaDef['nome'],
                            'valor_min' => $faixaDef['valor_min'],
                            'valor_max' => $faixaDef['valor_max'],
                            'ordem' => $faixaDef['ordem'],
                            'texto_email' => $faixaDef['texto_email'],
                            'ativo' => true,
                        ]
                    );
                }
            }
        });
    }
}
