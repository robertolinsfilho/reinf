-- Contato do contribuinte para R-1000 (nmCtt / cpfCtt)
ALTER TABLE contribuintes
    ADD COLUMN nome_contato VARCHAR(70) NULL AFTER telefone,
    ADD COLUMN cpf_contato VARCHAR(11) NULL AFTER nome_contato;
