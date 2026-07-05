-- ============================================
-- EFD-Reinf · Schema v2.1.2 COMPLETO
-- Tudo em um único arquivo para primeira execução
-- ============================================

CREATE DATABASE IF NOT EXISTS efd_reinf
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE efd_reinf;

-- ─── Usuários ────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin','usuario') DEFAULT 'usuario',
    ativo TINYINT(1) DEFAULT 1,
    trial_expira DATE NULL,
    ultimo_acesso DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── Contribuintes ───────────────────────────
CREATE TABLE IF NOT EXISTS contribuintes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cnpj VARCHAR(14) NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    tipo_contribuinte ENUM('1','2') DEFAULT '1',
    classificacao_tributos VARCHAR(10) DEFAULT '01',
    ie VARCHAR(20),
    cnae_principal VARCHAR(7),
    logradouro VARCHAR(255),
    municipio VARCHAR(100),
    uf CHAR(2),
    cep VARCHAR(8),
    email VARCHAR(150),
    telefone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_cnpj (cnpj)
) ENGINE=InnoDB;

-- ─── Competências ────────────────────────────
CREATE TABLE IF NOT EXISTS competencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribuinte_id INT NOT NULL,
    periodo VARCHAR(7) NOT NULL,
    status ENUM('aberto','fechado','transmitido','retificado') DEFAULT 'aberto',
    num_recibo VARCHAR(50) NULL,
    data_envio DATETIME NULL,
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuinte_id) REFERENCES contribuintes(id) ON DELETE CASCADE,
    UNIQUE KEY uk_competencia (contribuinte_id, periodo)
) ENGINE=InnoDB;

-- ─── R-1070 Processos ────────────────────────
CREATE TABLE IF NOT EXISTS r1070_processos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribuinte_id INT NOT NULL,
    tipo_processo TINYINT NOT NULL,
    numero_processo VARCHAR(30) NOT NULL,
    indicador_autoria TINYINT NULL,
    uf_vara CHAR(2) NULL,
    cod_municipio VARCHAR(7) NULL,
    id_vara VARCHAR(4) NULL,
    indicador_susp_exig TINYINT DEFAULT 0,
    data_decisao DATE NULL,
    indicador_deposito TINYINT DEFAULT 0,
    data_inclusao DATE NOT NULL,
    descricao TEXT,
    status ENUM('ativo','encerrado','suspenso') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuinte_id) REFERENCES contribuintes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── R-2010 ──────────────────────────────────
CREATE TABLE IF NOT EXISTS r2010 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_prestador VARCHAR(14) NOT NULL,
    razao_social_prestador VARCHAR(200),
    tipo_insc_prestador ENUM('1','2') DEFAULT '1',
    num_documento VARCHAR(50),
    serie VARCHAR(5),
    data_emissao DATE,
    valor_bruto DECIMAL(15,2) DEFAULT 0.00,
    valor_base_retencao DECIMAL(15,2) DEFAULT 0.00,
    valor_retencao DECIMAL(15,2) DEFAULT 0.00,
    valor_retencao_ajustada DECIMAL(15,2) DEFAULT 0.00,
    valor_desc_senar DECIMAL(15,2) DEFAULT 0.00,
    cod_servico VARCHAR(10),
    tp_servico TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── R-2020 ──────────────────────────────────
CREATE TABLE IF NOT EXISTS r2020 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_tomador VARCHAR(14) NOT NULL,
    razao_social_tomador VARCHAR(200),
    tipo_insc_tomador ENUM('1','2','3','4') DEFAULT '1',
    num_documento VARCHAR(50),
    serie VARCHAR(5),
    data_emissao DATE,
    valor_bruto DECIMAL(15,2) DEFAULT 0.00,
    valor_base_retencao DECIMAL(15,2) DEFAULT 0.00,
    valor_retencao DECIMAL(15,2) DEFAULT 0.00,
    valor_retencao_ajustada DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── R-2060 ──────────────────────────────────
CREATE TABLE IF NOT EXISTS r2060 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnae VARCHAR(7) NOT NULL,
    valor_rec_bruta DECIMAL(15,2) DEFAULT 0.00,
    valor_exclusoes DECIMAL(15,2) DEFAULT 0.00,
    valor_base_calculo DECIMAL(15,2) DEFAULT 0.00,
    aliquota DECIMAL(5,2) DEFAULT 0.00,
    valor_cprb DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── R-4010 ──────────────────────────────────
CREATE TABLE IF NOT EXISTS r4010 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cpf_beneficiario VARCHAR(11) NOT NULL,
    nome_beneficiario VARCHAR(200),
    natureza_rendimento VARCHAR(5) NOT NULL,
    data_pagamento DATE NOT NULL,
    valor_bruto DECIMAL(15,2) DEFAULT 0.00,
    valor_ir DECIMAL(15,2) DEFAULT 0.00,
    valor_base_ir DECIMAL(15,2) DEFAULT 0.00,
    valor_deducao DECIMAL(15,2) DEFAULT 0.00,
    descricao_pagamento VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── R-4020 (formato oficial completo) ───────
CREATE TABLE IF NOT EXISTS r4020 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    cnpj_contribuinte VARCHAR(14) NULL,
    cnpj_beneficiario VARCHAR(14) NOT NULL,
    num_nfs VARCHAR(50) NULL,
    periodo_apuracao DATE NULL,
    razao_social_beneficiario VARCHAR(200),
    natureza_rendimento VARCHAR(5) NOT NULL,
    cod_tipo_servico VARCHAR(5) NULL,
    cod_pais VARCHAR(3) NULL,
    data_pagamento DATE NOT NULL,
    valor_bruto DECIMAL(15,2) DEFAULT 0.00,
    valor_ir DECIMAL(15,2) DEFAULT 0.00,
    vl_csrf_agregado DECIMAL(15,2) DEFAULT 0.00,
    valor_csll DECIMAL(15,2) DEFAULT 0.00,
    valor_cofins DECIMAL(15,2) DEFAULT 0.00,
    valor_pis DECIMAL(15,2) DEFAULT 0.00,
    valor_base_ir DECIMAL(15,2) DEFAULT 0.00,
    identificador_adicional VARCHAR(100) NULL,
    indicador_fci_scp TINYINT NULL,
    cnpj_fci_scp VARCHAR(14) NULL,
    percentual_scp DECIMAL(5,2) NULL,
    indicador_judicial TINYINT DEFAULT 0,
    numero_processo VARCHAR(30) NULL,
    indicador_origem_recurso TINYINT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Naturezas Rendimento ────────────────────
CREATE TABLE IF NOT EXISTS naturezas_rendimento (
    codigo VARCHAR(5) PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    aplicavel_pf TINYINT(1) DEFAULT 1,
    aplicavel_pj TINYINT(1) DEFAULT 1,
    grupo VARCHAR(50),
    ativo TINYINT(1) DEFAULT 1,
    tributo VARCHAR(10) NULL,
    tabela_origem VARCHAR(10) DEFAULT '01'
) ENGINE=InnoDB;

-- ─── Certificados ────────────────────────────
CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribuinte_id INT NOT NULL,
    nome_arquivo VARCHAR(255),
    caminho VARCHAR(500),
    cnpj_certificado VARCHAR(14),
    titular VARCHAR(255),
    validade DATE,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuinte_id) REFERENCES contribuintes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Transmissões ────────────────────────────
CREATE TABLE IF NOT EXISTS transmissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_operacao ENUM('envio','consulta','fechamento') NOT NULL,
    evento VARCHAR(10) NOT NULL DEFAULT '',
    protocolo VARCHAR(50),
    numero_recibo VARCHAR(50),
    recibo_retornado VARCHAR(50) NULL,
    xml_enviado LONGTEXT,
    xml_retorno LONGTEXT,
    codigo_retorno VARCHAR(10),
    descricao_retorno TEXT,
    sucesso TINYINT(1) DEFAULT 0,
    tempo_resposta_ms INT,
    ambiente TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ─── Importações ─────────────────────────────
CREATE TABLE IF NOT EXISTS importacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    arquivo_nome VARCHAR(255),
    evento VARCHAR(10),
    total_registros INT DEFAULT 0,
    registros_importados INT DEFAULT 0,
    status ENUM('processando','sucesso','erro') DEFAULT 'processando',
    log_erros TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Arquivos gerados ────────────────────────
CREATE TABLE IF NOT EXISTS arquivos_gerados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    evento VARCHAR(10),
    nome_arquivo VARCHAR(255),
    caminho VARCHAR(500),
    tamanho INT,
    hash_md5 VARCHAR(32),
    xml_conteudo LONGTEXT,
    assinado TINYINT(1) DEFAULT 0,
    ind_retif TINYINT(1) DEFAULT 1,
    nr_recibo_original VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SEEDS
-- ============================================

-- Admin (senha: admin123)
INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES
('Administrador', 'admin@efdreinf.com.br',
 '$2y$10$i7YbP/ylBPLklB6sh..fqO.kHgA7o8vKHVinZOa9fvbnmAqSzT5Oi',
 'admin', 1);

-- Tabela 01 - Naturezas PF (R-4010)
INSERT IGNORE INTO naturezas_rendimento (codigo, descricao, aplicavel_pf, aplicavel_pj, grupo, tabela_origem) VALUES
('10001', 'Remuneração de trabalho assalariado, aposentadoria, reserva ou reforma', 1, 0, 'Trabalho', '01'),
('10002', 'Décimo terceiro salário', 1, 0, 'Trabalho', '01'),
('10003', 'Diárias e ajuda de custo', 1, 0, 'Trabalho', '01'),
('10005', 'Participação dos trabalhadores nos lucros ou resultados (PLR)', 1, 0, 'Trabalho', '01'),
('10009', 'Rendimentos recebidos acumuladamente (RRA)', 1, 0, 'Trabalho', '01'),
('12001', 'Pró-labore, remuneração de conselheiros e diretores', 1, 0, 'Serviços', '01'),
('12002', 'Honorários profissionais a autônomos', 1, 0, 'Serviços', '01'),
('12003', 'Comissões e corretagens', 1, 1, 'Serviços', '01'),
('12040', 'Aluguéis de bens imóveis', 1, 1, 'Serviços', '01'),
('12045', 'Royalties', 1, 1, 'Serviços', '01'),
('99001', 'Outros rendimentos não classificados', 1, 1, 'Outros', '01');