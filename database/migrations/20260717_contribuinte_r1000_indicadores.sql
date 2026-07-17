-- Indicadores do R-1000 (infoCadastro) no contribuinte
ALTER TABLE contribuintes
    ADD COLUMN ind_escrituracao TINYINT NOT NULL DEFAULT 0 AFTER cpf_contato,
    ADD COLUMN ind_desoneracao TINYINT NOT NULL DEFAULT 0 AFTER ind_escrituracao,
    ADD COLUMN ind_acordo_isen_multa TINYINT NOT NULL DEFAULT 0 AFTER ind_desoneracao,
    ADD COLUMN ind_sit_pj TINYINT NOT NULL DEFAULT 0 AFTER ind_acordo_isen_multa;

-- Default de classificação: PJ em geral (Tabela 08), se ainda estiver em valor legado inválido
UPDATE contribuintes
SET classificacao_tributos = '99'
WHERE classificacao_tributos IS NULL
   OR classificacao_tributos = ''
   OR classificacao_tributos NOT IN (
        '01','02','03','04','06','07','08','09','10','11','13','14','21','22','60','70','80','85','99'
   );
