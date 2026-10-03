-- Libera a faixa de desempenho dos 22 alunos exportados em 03/10/2026.
-- Apaga o histórico de contatos e os campos que travam a ação em Ok.
-- Na primeira execução, copia contatos_aluno para contatos_aluno_backup_20261003.
-- Para voltar ao estado de produção: database/scripts/restaurar-contatos-producao-20261003.sql
--
-- sqlite3 database/database.sqlite < database/scripts/limpar-contatos-para-faixa.sql
--
-- Aluno inativo (Larissa Gomes, Isadora Gomes Silva, Walder) não entra na faixa:
-- sem contato resolvido, o painel mostra Restabelecer contato.
-- Nayara Oliveira não aparece no dashboard.

BEGIN;

CREATE TABLE IF NOT EXISTS contatos_aluno_backup_20261003 (
    id INTEGER PRIMARY KEY,
    aluno_id INTEGER NOT NULL,
    user_id INTEGER,
    observacao TEXT,
    ocorrido_em TEXT,
    created_at TEXT,
    updated_at TEXT
);

-- Só na primeira execução: uma segunda limpeza não mistura contatos novos no backup.
INSERT INTO contatos_aluno_backup_20261003 (id, aluno_id, user_id, observacao, ocorrido_em, created_at, updated_at)
SELECT c.id, c.aluno_id, c.user_id, c.observacao, c.ocorrido_em, c.created_at, c.updated_at
FROM contatos_aluno AS c
WHERE c.aluno_id IN (23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44)
  AND NOT EXISTS (SELECT 1 FROM contatos_aluno_backup_20261003 LIMIT 1);

DELETE FROM contatos_aluno WHERE aluno_id IN (23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44);

UPDATE alunos
SET ultimo_contato_em = NULL,
    ultima_observacao = NULL,
    proximo_contato_em = NULL,
    acao_resolvida = NULL,
    acao_resolvida_assinatura = NULL
WHERE id IN (23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44);

COMMIT;
