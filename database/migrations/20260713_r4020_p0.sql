-- P0 R-4020: bases por tributo, origem judicial, recibo por arquivo

ALTER TABLE r4020
    ADD COLUMN valor_base_csll DECIMAL(15,2) DEFAULT 0.00 AFTER valor_base_ir,
    ADD COLUMN valor_base_cofins DECIMAL(15,2) DEFAULT 0.00 AFTER valor_base_csll,
    ADD COLUMN valor_base_pis DECIMAL(15,2) DEFAULT 0.00 AFTER valor_base_cofins,
    ADD COLUMN valor_base_agreg DECIMAL(15,2) DEFAULT 0.00 AFTER valor_base_pis,
    ADD COLUMN cnpj_origem_recurso VARCHAR(14) NULL AFTER indicador_origem_recurso;

ALTER TABLE arquivos_gerados
    ADD COLUMN id_evento VARCHAR(52) NULL AFTER evento,
    ADD COLUMN protocolo VARCHAR(50) NULL AFTER nr_recibo_original,
    ADD COLUMN nr_recibo_retornado VARCHAR(50) NULL AFTER protocolo;

-- Normaliza origem da tabela oficial (Tabela 01 dos leiautes)
UPDATE naturezas_rendimento SET tabela_origem = '01' WHERE tabela_origem = '4020';
