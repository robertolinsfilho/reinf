-- R-2010: indCPRB (0 = retenção 11%, 1 = contribuinte CPRB / 3,5%)
ALTER TABLE r2010
    ADD COLUMN ind_cprb TINYINT NOT NULL DEFAULT 0 AFTER tp_servico;
