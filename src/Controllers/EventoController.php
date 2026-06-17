// ─── R-4010 · Pagamentos PF ─────────────────────────────

    public function r4010(): void
    {
        $this->requireLogin();
        $cid         = (int) $this->get('competencia_id');
        $competencia = $this->getCompetencia($cid);

        $stmt = $this->db->prepare("SELECT * FROM r4010 WHERE competencia_id = ? ORDER BY data_pagamento DESC");
        $stmt->execute([$cid]);
        $registros = $stmt->fetchAll();

        $this->view('pages/eventos/r4010', [
            'pageTitle'   => 'R-4010 – Pagamentos/Créditos PF (IRRF)',
            'competencia' => $competencia,
            'registros'   => $registros,
        ]);
    }

    public function salvarR4010(): void
    {
        $this->requireLogin();
        $d   = $_POST;
        $cid = (int) ($d['competencia_id'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO r4010
                (competencia_id, cpf_beneficiario, nome_beneficiario, natureza_rendimento,
                 data_pagamento, valor_bruto, valor_ir, valor_base_ir, valor_deducao, descricao_pagamento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cid,
            preg_replace('/\D/', '', $d['cpf_beneficiario'] ?? ''),
            $d['nome_beneficiario'] ?? '',
            $d['natureza_rendimento'] ?? '',
            $d['data_pagamento'] ?? date('Y-m-d'),
            $this->parseMoney($d['valor_bruto'] ?? '0'),
            $this->parseMoney($d['valor_ir'] ?? '0'),
            $this->parseMoney($d['valor_base_ir'] ?? '0'),
            $this->parseMoney($d['valor_deducao'] ?? '0'),
            $d['descricao_pagamento'] ?? '',
        ]);

        $this->redirect("/eventos/r4010?competencia_id={$cid}", 'Pagamento PF adicionado!', 'success');
    }

    public function excluirR4010(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id', 0));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id', 0));
        $this->db->prepare("DELETE FROM r4010 WHERE id = ?")->execute([$id]);
        $this->redirect("/eventos/r4010?competencia_id={$cid}", 'Registro excluído.', 'success');
    }

    // ─── R-4020 · Pagamentos PJ ─────────────────────────────

    public function r4020(): void
    {
        $this->requireLogin();
        $cid         = (int) $this->get('competencia_id');
        $competencia = $this->getCompetencia($cid);

        $stmt = $this->db->prepare("SELECT * FROM r4020 WHERE competencia_id = ? ORDER BY data_pagamento DESC");
        $stmt->execute([$cid]);
        $registros = $stmt->fetchAll();

        $this->view('pages/eventos/r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ (IRRF/CSLL/PIS/COFINS)',
            'competencia' => $competencia,
            'registros'   => $registros,
        ]);
    }

    public function salvarR4020(): void
    {
        $this->requireLogin();
        $d   = $_POST;
        $cid = (int) ($d['competencia_id'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO r4020
                (competencia_id, cnpj_beneficiario, razao_social_beneficiario, natureza_rendimento,
                 data_pagamento, valor_bruto, valor_ir, valor_csll, valor_cofins, valor_pis, valor_base_ir)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cid,
            preg_replace('/\D/', '', $d['cnpj_beneficiario'] ?? ''),
            $d['razao_social_beneficiario'] ?? '',
            $d['natureza_rendimento'] ?? '',
            $d['data_pagamento'] ?? date('Y-m-d'),
            $this->parseMoney($d['valor_bruto'] ?? '0'),
            $this->parseMoney($d['valor_ir'] ?? '0'),
            $this->parseMoney($d['valor_csll'] ?? '0'),
            $this->parseMoney($d['valor_cofins'] ?? '0'),
            $this->parseMoney($d['valor_pis'] ?? '0'),
            $this->parseMoney($d['valor_base_ir'] ?? '0'),
        ]);

        $this->redirect("/eventos/r4020?competencia_id={$cid}", 'Pagamento PJ adicionado!', 'success');
    }

    public function excluirR4020(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id', 0));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id', 0));
        $this->db->prepare("DELETE FROM r4020 WHERE id = ?")->execute([$id]);
        $this->redirect("/eventos/r4020?competencia_id={$cid}", 'Registro excluído.', 'success');
    }

    // ─── Helper ──────────────────────────────────────────────

    private function parseMoney(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $valor);
    }