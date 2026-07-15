-- R-2055 – Aquisição de produção rural
CREATE TABLE IF NOT EXISTS r2055 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    tp_insc_adquirente ENUM('1','3') NOT NULL DEFAULT '1',
    nr_insc_adquirente VARCHAR(14) NOT NULL,
    tp_insc_produtor ENUM('1','2') NOT NULL DEFAULT '1',
    nr_insc_produtor VARCHAR(14) NOT NULL,
    ind_opc_cp CHAR(1) NULL COMMENT 'S = opção folha; NULL = comercialização',
    ind_aquis CHAR(1) NOT NULL DEFAULT '1',
    valor_bruto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valor_cp_desc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valor_rat_desc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valor_senar_desc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE,
    INDEX idx_r2055_comp (competencia_id),
    INDEX idx_r2055_prod (nr_insc_produtor)
) ENGINE=InnoDB;
