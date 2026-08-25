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
                        'texto_email' => 'Você estudou em todos os {Y} dias analisados, sem deixar nenhum dia zerado. Sua constância foi excelente, parabéns! Continue preservando essa dedicação nos próximos períodos.',
                    ],
                    [
                        'codigo' => 'bom',
                        'nome' => 'Bom',
                        'valor_min' => 1,
                        'valor_max' => 3,
                        'ordem' => 2,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar. Sua constância foi boa. Você conseguiu manter uma frequência satisfatória durante o período, mas pode melhorar.',
                    ],
                    [
                        'codigo' => 'brigando',
                        'nome' => 'Brigando com a constância',
                        'valor_min' => 4,
                        'valor_max' => 10,
                        'ordem' => 3,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar. Vejo que a briga com a constância nos estudos está forte, ein?! No próximo período, procure manter um contato mais frequente com o seu compromisso pessoal.',
                    ],
                    [
                        'codigo' => 'critico',
                        'nome' => 'Crítico',
                        'valor_min' => 11,
                        'valor_max' => null,
                        'ordem' => 4,
                        'texto_email' => 'Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar. Sua constância está em nível crítico. Você ficou muitos dias sem contato com os estudos e sua prioridade no próximo período deve ser retornar o quanto antes. Ou você se esqueceu que quer mudar de vida?',
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
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões durante o período. Esse volume está em nível crítico e é insuficiente para avaliarmos seu percentual de acertos com segurança. Sua prioridade para o próximo período deve ser aumentar a quantidade de questões realizadas.',
                    ],
                    [
                        'codigo' => 'volume_baixo',
                        'nome' => 'Volume baixo',
                        'valor_min' => 50,
                        'valor_max' => 99,
                        'ordem' => 2,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões durante o período. Esse volume está abaixo do recomendado e torna a análise do seu percentual de acertos pouco conclusiva. Procure aumentar a quantidade de questões para pelo menos 100 no próximo período para termos uma avaliação mais segura do seu desempenho.',
                    ],
                    [
                        'codigo' => 'volume_suficiente',
                        'nome' => 'Volume suficiente',
                        'valor_min' => 100,
                        'valor_max' => 500,
                        'ordem' => 3,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões durante o período. Esse volume é suficiente para avaliarmos seu percentual de acertos com maior segurança.',
                    ],
                    [
                        'codigo' => 'volume_alto',
                        'nome' => 'Volume alto',
                        'valor_min' => 501,
                        'valor_max' => null,
                        'ordem' => 4,
                        'texto_email' => '{NOME}, você realizou {TOTAL_QUESTOES} questões durante o período. Você arrebentou! Esse é um volume alto de treinamento e permite avaliar seu percentual de acertos com mais segurança. Parabéns pelo empenho!',
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
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% geral de acertos no período. Esse resultado foi sinalizado como CRÍTICO no seu acompanhamento e merece uma análise mais próxima.',
                    ],
                    [
                        'codigo' => 'alerta',
                        'nome' => 'Alerta',
                        'valor_min' => 60.01,
                        'valor_max' => 70,
                        'ordem' => 2,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% geral de acertos no período. Esse resultado foi sinalizado como um PONTO DE ATENÇÃO no seu acompanhamento. Você já demonstra algum domínio dos conteúdos, mas ainda existe uma quantidade relevante de erros que precisa ser compreendida.',
                    ],
                    [
                        'codigo' => 'mediano',
                        'nome' => 'Mediano',
                        'valor_min' => 70.01,
                        'valor_max' => 79.99,
                        'ordem' => 3,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Esse resultado foi classificado como MEDIANO no seu acompanhamento. Você já construiu uma base importante, mas ainda existe uma quantidade de erros que impede um desempenho mais seguro.',
                    ],
                    [
                        'codigo' => 'muito_bom',
                        'nome' => 'Muito bom',
                        'valor_min' => 80,
                        'valor_max' => 89.99,
                        'ordem' => 4,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Esse resultado foi classificado como MUITO BOM e mostra que você está construindo um desempenho consistente. Neste momento, sua prioridade não é mudar toda a estratégia, mas preservar o que está funcionando e observar os assuntos que ainda concentram seus erros. Vamos continuar acompanhando esses pontos para que você avance sem perder os resultados já conquistados.',
                    ],
                    [
                        'codigo' => 'excelente',
                        'nome' => 'Excelente',
                        'valor_min' => 90,
                        'valor_max' => 100,
                        'ordem' => 5,
                        'texto_email' => '{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período. Esse resultado foi classificado como EXCELENTE e demonstra um domínio elevado dos conteúdos praticados. O resultado foi positivo, mas o acompanhamento continua: vamos observar se esse desempenho se mantém. Sua prioridade agora é preservar esse nível, continuar corrigindo os erros pontuais e evitar que os bons resultados tragam acomodação.',
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
                        'texto_email' => "{LISTA_ASSUNTOS}\n\nEsses resultados foram sinalizados como possíveis gargalos e merecem uma análise mais próxima.\nIrei entrar em contato para entender a origem dos erros e definir a melhor correção de rota. Caso queira adiantar essa conversa, você pode utilizar o botão abaixo.",
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
