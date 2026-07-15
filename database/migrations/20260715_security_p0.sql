-- Segurança: forçar troca de senha no primeiro acesso / seed admin
ALTER TABLE usuarios
    ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo;

UPDATE usuarios
SET force_password_change = 1
WHERE email = 'admin@efdreinf.com.br';
