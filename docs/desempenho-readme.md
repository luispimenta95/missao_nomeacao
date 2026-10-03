# Gestão de desempenho — parâmetros do texto

No admin (`/admin/desempenho`), cada **faixa** tem o texto de e-mail que já existia e pode receber outros. Os valores reais entram pelos placeholders abaixo.

## Rotação

O texto principal da faixa é mantido. Ele é o primeiro da lista e entra quando a faixa do aluno muda.

No dia do relatório, a escolha olha a última faixa gravada no aluno e a faixa calculada naquele momento, eixo por eixo (constância, volume, percentual e assunto):

- Se a faixa é a mesma, usa um texto dessa faixa que ainda não foi enviado na rodada atual. Quando todos já foram usados, a rodada recomeça pelo texto principal.
- Se a faixa mudou, não consulta esse histórico: entra o texto principal da faixa nova, porque o texto já é outro.

Cada uso fica em `usos_texto_desempenho`, com o id do texto, a faixa, o ciclo e o período (`AAAA-MM-N`). Repetir o mesmo período reaproveita o texto já escolhido. A migração marca, para cada aluno, o texto principal da última faixa como já usado, para a próxima quinzena na mesma faixa seguir para o próximo texto.

## Placeholders gerais

| Placeholder | O que vira no texto |
|---|---|
| `{NOME}` ou `{FULANO}` / `[Fulano]` | Primeiro nome do aluno (ex.: Lara) |
| `{TOTAL_QUESTOES}` | Total de questões do período |
| `{PERCENTUAL_ACERTOS}` | % geral de acertos (ex.: 78,1) |
| `{LISTA_ASSUNTOS}` | Lista com bullets dos assuntos abaixo da média (quantidade variável) |
| `{ASSUNTO}` | Nome do assunto (uso pontual; na prática a lista já vem em `{LISTA_ASSUNTOS}`) |
| `{DISCIPLINA}` | Nome da disciplina |
| `{PERCENTUAL}` | % daquele assunto |

## Só na Constância

| Placeholder | Significado |
|---|---|
| `{Y}` ou `{DIAS_ANALISADOS}` | Dias do período analisado |
| `{X}` ou `{DIAS_ESTUDADOS}` | Dias em que estudou (> 0 h) |
| `{Z}` ou `{DIAS_FALHADOS}` | Dias sem estudar (`Y − X`) |

## Como usar

Escreva o texto normalmente e coloque o placeholder onde o número/nome deve aparecer.

### Constância

```text
Você estudou em {X} dos {Y} dias analisados, deixando {Z} dias sem estudar.
```

### Volume de questões

```text
{NOME}, você realizou {TOTAL_QUESTOES} questões no período.
```

### % geral

```text
{NOME}, você alcançou {PERCENTUAL_ACERTOS}% de acertos no período.
```

### Assunto (críticos e abaixo da média em blocos separados)

Assuntos com rendimento ≤ 60% entram no bloco **crítico** (com botão *Quero adiantar minha análise*). Assuntos entre 61% e 75% entram no bloco **abaixo da média**. Use `{LISTA_ASSUNTOS}` e o texto de fechamento:

```text
{LISTA_ASSUNTOS}

Esses dados vão ficar em acompanhamento nos próximos relatórios.
A prioridade agora é reduzir os erros recorrentes e verificar se o percentual evolui nos próximos períodos. Caso o desempenho permaneça nessa faixa ou apresente queda, faremos uma análise mais próxima para definir a correção de rota, ok?
```

Cada bullet é gerado assim:

```text
• No assunto {ASSUNTO}, você alcançou {PERCENTUAL}% de acertos.
```
