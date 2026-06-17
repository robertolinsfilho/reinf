-- Banco de dados EFD REINF
CREATE DATABASE IF NOT EXISTS efd_reinf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE efd_reinf;

-- Usuários do sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin','usuario') DEFAULT 'usuario',
    ativo TINYINT(1) DEFAULT 1,
    trial_expira DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Empresas/Contribuintes
CREATE TABLE IF NOT EXISTS contribuintes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cnpj VARCHAR(18) NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    tipo_contribuinte ENUM('1','2','3','4','5','6','7','8') DEFAULT '1' COMMENT '1=CNPJ,2=CPF,3=CAEPF,4=CNO,5=CGC,6=CEI,7=CNO,8=NIT',
    classificacao_tributos VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Competências (períodos de apuração)
CREATE TABLE IF NOT EXISTS competencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribuinte_id INT NOT NULL,
    periodo VARCHAR(7) NOT NULL COMMENT 'AAAA-MM',
    status ENUM('aberto','fechado','transmitido','retificado') DEFAULT 'aberto',
    num_recibo VARCHAR(50) NULL,
    data_envio DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuinte_id) REFERENCES contribuintes(id) ON DELETE CASCADE,
    UNIQUE KEY uk_competencia (contribuinte_id, periodo)
);

-- R-1000: Informações do Contribuinte
CREATE TABLE IF NOT EXISTS r1000 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_contribuinte VARCHAR(18) NOT NULL,
    classificacao_tributos VARCHAR(10),
    ind_estatuto_pj ENUM('0','1') DEFAULT '0',
    ind_cooperativa ENUM('0','1','2') DEFAULT '0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2010: Retenções na Fonte – INSS – Contratados
CREATE TABLE IF NOT EXISTS r2010 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_prestador VARCHAR(18) NOT NULL,
    razao_social_prestador VARCHAR(200),
    tipo_insc_prestador ENUM('1','2') DEFAULT '1' COMMENT '1=CNPJ,2=CPF',
    ind_observancia ENUM('0','1') DEFAULT '0',
    ind_desoneracao ENUM('N','S') DEFAULT 'N',
    num_documento VARCHAR(50),
    data_emissao DATE,
    valor_bruto DECIMAL(15,2) DEFAULT 0,
    valor_retencao DECIMAL(15,2) DEFAULT 0,
    valor_retencao_ajustada DECIMAL(15,2) DEFAULT 0,
    valor_desc_senar DECIMAL(15,2) DEFAULT 0,
    valor_rat DECIMAL(15,2) DEFAULT 0,
    cnpj_tomador VARCHAR(18),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2020: Retenções na Fonte – INSS – Contratantes
CREATE TABLE IF NOT EXISTS r2020 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_tomador VARCHAR(18) NOT NULL,
    razao_social_tomador VARCHAR(200),
    tipo_insc_tomador ENUM('1','2','3','4') DEFAULT '1',
    valor_bruto DECIMAL(15,2) DEFAULT 0,
    valor_retencao DECIMAL(15,2) DEFAULT 0,
    valor_retencao_ajustada DECIMAL(15,2) DEFAULT 0,
    num_documento VARCHAR(50),
    data_emissao DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2030: Recursos Recebidos por Associações Desportivas
CREATE TABLE IF NOT EXISTS r2030 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_fonte_pagadora VARCHAR(18) NOT NULL,
    razao_social VARCHAR(200),
    valor_total DECIMAL(15,2) DEFAULT 0,
    valor_retencao DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2040: Recursos Repassados para Associações Desportivas
CREATE TABLE IF NOT EXISTS r2040 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_associacao VARCHAR(18) NOT NULL,
    razao_social VARCHAR(200),
    valor_repassado DECIMAL(15,2) DEFAULT 0,
    valor_retencao DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2050: Comercialização da Produção Rural PJ
CREATE TABLE IF NOT EXISTS r2050 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_adquirente VARCHAR(18),
    cpf_produtor VARCHAR(14),
    razao_social VARCHAR(200),
    valor_comercializacao DECIMAL(15,2) DEFAULT 0,
    valor_contribuicao_previdenciaria DECIMAL(15,2) DEFAULT 0,
    valor_senar DECIMAL(15,2) DEFAULT 0,
    data_operacao DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2055: Aquisição de Produção Rural
CREATE TABLE IF NOT EXISTS r2055 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cpf_produtor VARCHAR(14) NOT NULL,
    nome_produtor VARCHAR(200),
    valor_aquisicao DECIMAL(15,2) DEFAULT 0,
    valor_retencao DECIMAL(15,2) DEFAULT 0,
    valor_senar DECIMAL(15,2) DEFAULT 0,
    data_aquisicao DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-2060: CPRB – Contribuição Previdenciária sobre Receita Bruta
CREATE TABLE IF NOT EXISTS r2060 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    ind_constr_civil ENUM('0','1') DEFAULT '0',
    cnae VARCHAR(10),
    valor_rec_bruta DECIMAL(15,2) DEFAULT 0,
    valor_rec_bruta_excl DECIMAL(15,2) DEFAULT 0,
    valor_base_calculo DECIMAL(15,2) DEFAULT 0,
    aliquota DECIMAL(5,2) DEFAULT 0,
    valor_contribuicao DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- R-3010: Receitas de Espetáculos Desportivos
CREATE TABLE IF NOT EXISTS r3010 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    data_espetaculo DATE NOT NULL,
    local_espetaculo VARCHAR(200),
    valor_ingresso DECIMAL(15,2) DEFAULT 0,
    valor_cota_patronal DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
);

-- Importações de Excel
CREATE TABLE IF NOT EXISTS importacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    arquivo_nome VARCHAR(255),
    evento VARCHAR(10) COMMENT 'R2010, R2020, etc.',
    total_registros INT DEFAULT 0,
    registros_importados INT DEFAULT 0,
    status ENUM('processando','sucesso','erro') DEFAULT 'processando',
    log_erros TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Arquivos gerados
CREATE TABLE IF NOT EXISTS arquivos_gerados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nome_arquivo VARCHAR(255),
    caminho VARCHAR(500),
    tamanho INT,
    hash_md5 VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Inserir usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, perfil) VALUES
('Administrador', 'admin@efdreinf.com.br', '$2y$12$LnFwWFQBQMmCbVJ3YyLHNO7pJQNQQqjqMqVfUwLwT.j3IKA6tXYuO', 'admin');
-- Senha padrão: admin123
