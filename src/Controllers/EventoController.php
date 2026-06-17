<?php

namespace App\Controllers;

class EventoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $this->view('pages/eventos/index', ['pageTitle' => 'Eventos REINF', 'flash' => $this->getFlash()]);
    }

    private function getCompetencia(int $id): array
    {
        $uid  = $_SESSION['usuario']['id'];
        $stmt = $this->db->prepare("
            SELECT c.*, co.razao_social, co.cnpj
            FROM competencias c JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE c.id = ? AND co.usuario_id = ?
        ");
        $stmt->execute([$id, $uid]);
        $comp = $stmt->fetch();
        if (!$comp) {
            $this->flash('erro', 'Competência não encontrada.');
            $this->redirect('/competencias');
        }
        return $comp;
    }

    public function r2010(): void
    {
        $this->requireLogin();
        $cid         = (int) $this->get('competencia_id');
        $competencia = $this->getCompetencia($cid);
        $stmt = $this->db->prepare("SELECT * FROM r2010 WHERE competencia_id = ? ORDER BY created_at DESC");
        $stmt->execute([$cid]);
        $registros = $stmt->fetchAll();
        $this->view('pages/eventos/r2010', [
            'pageTitle'   => 'R-2010 – Retenções INSS Contratados',
            'competencia' => $competencia,
            'registros'   => $registros,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2010(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id', 0);
        $this->getCompetencia($cid);

        $id = (int) $this->post('id', 0);
        $dados = [
            'cnpj_prestador'         => preg_replace('/\D/', '', $this->post('cnpj_prestador', '')),
            'razao_social_prestador' => $this->sanitize($this->post('razao_social_prestador', '')),
            'tipo_insc_prestador'    => $this->post('tipo_insc_prestador', '1'),
            'num_documento'          => $this->sanitize($this->post('num_documento', '')),
            'data_emissao'           => $this->post('data_emissao', null),
            'valor_bruto'            => (float) str_replace(',', '.', $this->post('valor_bruto', '0')),
            'valor_retencao'         => (float) str_replace(',', '.', $this->post('valor_retencao', '0')),
            'valor_desc_senar'       => (float) str_replace(',', '.', $this->post('valor_desc_senar', '0')),
        ];

        if ($id) {
            $stmt = $this->db->prepare("
                UPDATE r2010 SET cnpj_prestador=?, razao_social_prestador=?, tipo_insc_prestador=?,
                num_documento=?, data_emissao=?, valor_bruto=?, valor_retencao=?, valor_desc_senar=?
                WHERE id=? AND competencia_id=?
            ");
            $stmt->execute([...array_values($dados), $id, $cid]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO r2010 (competencia_id, cnpj_prestador, razao_social_prestador, tipo_insc_prestador,
                num_documento, data_emissao, valor_bruto, valor_retencao, valor_desc_senar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cid, ...array_values($dados)]);
        }

        $this->flash('sucesso', 'Registro R-2010 salvo com sucesso!');
        $this->redirect("/eventos/r2010?competencia_id={$cid}");
    }

    public function r2020(): void
    {
        $this->requireLogin();
        $cid         = (int) $this->get('competencia_id');
        $competencia = $this->getCompetencia($cid);
        $stmt = $this->db->prepare("SELECT * FROM r2020 WHERE competencia_id = ? ORDER BY created_at DESC");
        $stmt->execute([$cid]);
        $registros = $stmt->fetchAll();
        $this->view('pages/eventos/r2020', [
            'pageTitle'   => 'R-2020 – Retenções INSS Contratantes',
            'competencia' => $competencia,
            'registros'   => $registros,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id', 0);
        $this->getCompetencia($cid);

        $dados = [
            'cnpj_tomador'        => preg_replace('/\D/', '', $this->post('cnpj_tomador', '')),
            'razao_social_tomador' => $this->sanitize($this->post('razao_social_tomador', '')),
            'tipo_insc_tomador'   => $this->post('tipo_insc_tomador', '1'),
            'num_documento'       => $this->sanitize($this->post('num_documento', '')),
            'data_emissao'        => $this->post('data_emissao', null),
            'valor_bruto'         => (float) str_replace(',', '.', $this->post('valor_bruto', '0')),
            'valor_retencao'      => (float) str_replace(',', '.', $this->post('valor_retencao', '0')),
        ];

        $stmt = $this->db->prepare("
            INSERT INTO r2020 (competencia_id, cnpj_tomador, razao_social_tomador, tipo_insc_tomador,
            num_documento, data_emissao, valor_bruto, valor_retencao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cid, ...array_values($dados)]);

        $this->flash('sucesso', 'Registro R-2020 salvo com sucesso!');
        $this->redirect("/eventos/r2020?competencia_id={$cid}");
    }

    public function r2060(): void
    {
        $this->requireLogin();
        $cid         = (int) $this->get('competencia_id');
        $competencia = $this->getCompetencia($cid);
        $stmt = $this->db->prepare("SELECT * FROM r2060 WHERE competencia_id = ? ORDER BY created_at DESC");
        $stmt->execute([$cid]);
        $registros = $stmt->fetchAll();
        $this->view('pages/eventos/r2060', [
            'pageTitle'   => 'R-2060 – CPRB',
            'competencia' => $competencia,
            'registros'   => $registros,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2060(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id', 0);
        $this->getCompetencia($cid);

        $dados = [
            'ind_constr_civil'    => $this->post('ind_constr_civil', '0'),
            'cnae'                => $this->sanitize($this->post('cnae', '')),
            'valor_rec_bruta'     => (float) str_replace(',', '.', $this->post('valor_rec_bruta', '0')),
            'valor_rec_bruta_excl'=> (float) str_replace(',', '.', $this->post('valor_rec_bruta_excl', '0')),
            'aliquota'            => (float) str_replace(',', '.', $this->post('aliquota', '0')),
        ];
        $dados['valor_base_calculo'] = $dados['valor_rec_bruta'] - $dados['valor_rec_bruta_excl'];
        $dados['valor_contribuicao']  = round($dados['valor_base_calculo'] * ($dados['aliquota'] / 100), 2);

        $stmt = $this->db->prepare("
            INSERT INTO r2060 (competencia_id, ind_constr_civil, cnae, valor_rec_bruta,
            valor_rec_bruta_excl, valor_base_calculo, aliquota, valor_contribuicao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cid, ...array_values($dados)]);

        $this->flash('sucesso', 'Registro R-2060 salvo com sucesso!');
        $this->redirect("/eventos/r2060?competencia_id={$cid}");
    }

    // ─── R-4010 · Pagamentos PF ─────────────────────────────

    public function r4010(): void
    {
        $this->requireAuth();
        $competenciaId = (int) ($_GET['competencia_id'] ?? 0);
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM r4010 WHERE competencia_id = ? ORDER BY data_pagamento DESC");
        $stmt->execute([$competenciaId]);
        $registros = $stmt->fetchAll();

        $comp = $db->prepare("SELECT c.*, ct.cnpj, ct.razao_social FROM competencias c JOIN contribuintes ct ON ct.id = c.contribuinte_id WHERE c.id = ?");
        $comp->execute([$competenciaId]);

        $this->view('pages/eventos/r4010', [
            'registros'    => $registros,
            'competencia'  => $comp->fetch(),
        ]);
    }

    public function salvarR4010(): void
    {
        $this->requireAuth();
        $db = Database::getInstance();
        $d  = $_POST;

        $stmt = $db->prepare("
            INSERT INTO r4010
                (competencia_id, cpf_beneficiario, nome_beneficiario, natureza_rendimento, data_pagamento, valor_bruto, valor_ir, valor_base_ir, valor_deducao, descricao_pagamento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $d['competencia_id'],
            preg_replace('/\D/', '', $d['cpf_beneficiario']),
            $d['nome_beneficiario'] ?? '',
            $d['natureza_rendimento'] ?? '',
            $d['data_pagamento'],
            $this->parseMoney($d['valor_bruto'] ?? '0'),
            $this->parseMoney($d['valor_ir'] ?? '0'),
            $this->parseMoney($d['valor_base_ir'] ?? '0'),
            $this->parseMoney($d['valor_deducao'] ?? '0'),
            $d['descricao_pagamento'] ?? '',
        ]);

        $this->redirect("/eventos/r4010?competencia_id={$d['competencia_id']}", 'Pagamento PF adicionado!', 'success');
    }

    public function excluirR4010(): void
    {
        $this->requireAuth();
        $id   = (int) ($_POST['id'] ?? 0);
        $comp = (int) ($_POST['competencia_id'] ?? 0);
        Database::getInstance()->prepare("DELETE FROM r4010 WHERE id = ?")->execute([$id]);
        $this->redirect("/eventos/r4010?competencia_id={$comp}", 'Registro excluído.', 'success');
    }

    // ─── R-4020 · Pagamentos PJ ─────────────────────────────

    public function r4020(): void
    {
        $this->requireAuth();
        $competenciaId = (int) ($_GET['competencia_id'] ?? 0);
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM r4020 WHERE competencia_id = ? ORDER BY data_pagamento DESC");
        $stmt->execute([$competenciaId]);
        $registros = $stmt->fetchAll();

        $comp = $db->prepare("SELECT c.*, ct.cnpj, ct.razao_social FROM competencias c JOIN contribuintes ct ON ct.id = c.contribuinte_id WHERE c.id = ?");
        $comp->execute([$competenciaId]);

        $this->view('pages/eventos/r4020', [
            'registros'   => $registros,
            'competencia' => $comp->fetch(),
        ]);
    }

    public function salvarR4020(): void
    {
        $this->requireAuth();
        $db = Database::getInstance();
        $d  = $_POST;

        $stmt = $db->prepare("
            INSERT INTO r4020
                (competencia_id, cnpj_beneficiario, razao_social_beneficiario, natureza_rendimento, data_pagamento, valor_bruto, valor_ir, valor_csll, valor_cofins, valor_pis, valor_base_ir)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $d['competencia_id'],
            preg_replace('/\D/', '', $d['cnpj_beneficiario']),
            $d['razao_social_beneficiario'] ?? '',
            $d['natureza_rendimento'] ?? '',
            $d['data_pagamento'],
            $this->parseMoney($d['valor_bruto'] ?? '0'),
            $this->parseMoney($d['valor_ir'] ?? '0'),
            $this->parseMoney($d['valor_csll'] ?? '0'),
            $this->parseMoney($d['valor_cofins'] ?? '0'),
            $this->parseMoney($d['valor_pis'] ?? '0'),
            $this->parseMoney($d['valor_base_ir'] ?? '0'),
        ]);

        $this->redirect("/eventos/r4020?competencia_id={$d['competencia_id']}", 'Pagamento PJ adicionado!', 'success');
    }

    public function excluirR4020(): void
    {
        $this->requireAuth();
        $id   = (int) ($_POST['id'] ?? 0);
        $comp = (int) ($_POST['competencia_id'] ?? 0);
        Database::getInstance()->prepare("DELETE FROM r4020 WHERE id = ?")->execute([$id]);
        $this->redirect("/eventos/r4020?competencia_id={$comp}", 'Registro excluído.', 'success');
    }

    // ─── Helper ──────────────────────────────────────────────

    private function parseMoney(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $valor);
    }
}
