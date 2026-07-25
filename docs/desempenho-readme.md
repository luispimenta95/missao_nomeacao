# Gestão de desempenho — parâmetros do texto

No admin (`/admin/desempenho`), cada **faixa** tem um texto de e-mail. Os valores reais entram pelos placeholders abaixo.

## Placeholders gerais

| Placeholder | O que vira no texto |
|---|---|
| `{NOME}` ou `{FULANO}` / `[Fulano]` | Primeiro nome do aluno (ex.: Lara) |
| `{TOTAL_QUESTOES}` | Total de questões do período |
| `{PERCENTUAL_ACERTOS}` | % geral de acertos (ex.: 78,1) |
| `{ASSUNTO}` | Nome do assunto |
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

### Assunto

```text
No assunto {ASSUNTO}, você alcançou {PERCENTUAL}% de acertos.
```
