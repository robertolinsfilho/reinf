<?php

namespace App\Controllers;

use App\Models\EventoRepository;
use App\Models\CompetenciaRepository;

class EventoController extends BaseController
{
    private EventoRepository $eventos;
    private CompetenciaRepository $competencias;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->eventos      = new EventoRepository($this->db);
        $this->competencias = new CompetenciaRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $this->view('pages/eventos/index', [
            'pageTitle' => 'Eventos REINF',
            'flash'     => $this->getFlash(),
        ]);
    }

    private function getComp(int $id): array
    {
        $comp = $this->competencias->findWithContribuinte($id, $this->userId());
        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }
        return $comp;
    }

    // ═══ R-2010 ══════════════════════════════════════════════

    public function r2010(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $this->view('pages/eventos/r2010', [
            'pageTitle'   => 'R-2010 – Retenções INSS Contratados',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r2010', $cid),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2010(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $id    = (int) $this->post('id', 0);
            $dados = [
                'cnpj_prestador'         => $this->postCnpj('cnpj_prestador'),
                'razao_social_prestador' => $this->sanitize($this->post('razao_social_prestador', '')),
                'tipo_insc_prestador'    => $this->post('tipo_insc_prestador', '1'),
                'num_documento'          => $this->sanitize($this->post('num_documento', '')),
                'data_emissao'           => $this->post('data_emissao'),
                'valor_bruto'            => $this->postMoney('valor_bruto'),
                'valor_retencao'         => $this->postMoney('valor_retencao'),
                'valor_desc_senar'       => $this->postMoney('valor_desc_senar'),
            ];

            if ($id) {
                $this->eventos->atualizar('r2010', $id, $cid, $dados);
            } else {
                $this->eventos->inserir('r2010', ['competencia_id' => $cid, ...$dados]);
            }

            $this->redirect("/eventos/r2010?competencia_id={$cid}", 'R-2010 salvo!', 'sucesso');
        }, "/eventos/r2010?competencia_id={$cid}", 'Erro ao salvar R-2010');
    }

    public function excluirR2010(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id'));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id'));
        $this->getComp($cid);
        $this->eventos->excluir('r2010', $id, $cid);
        $this->redirect("/eventos/r2010?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-2020 ══════════════════════════════════════════════

    public function r2020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $this->view('pages/eventos/r2020', [
            'pageTitle'   => 'R-2020 – Retenções INSS Contratantes',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r2020', $cid),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $this->eventos->inserir('r2020', [
                'competencia_id'       => $cid,
                'cnpj_tomador'         => $this->postCnpj('cnpj_tomador'),
                'razao_social_tomador' => $this->sanitize($this->post('razao_social_tomador', '')),
                'tipo_insc_tomador'    => $this->post('tipo_insc_tomador', '1'),
                'num_documento'        => $this->sanitize($this->post('num_documento', '')),
                'data_emissao'         => $this->post('data_emissao'),
                'valor_bruto'          => $this->postMoney('valor_bruto'),
                'valor_retencao'       => $this->postMoney('valor_retencao'),
            ]);
            $this->redirect("/eventos/r2020?competencia_id={$cid}", 'R-2020 salvo!', 'sucesso');
        }, "/eventos/r2020?competencia_id={$cid}");
    }

    public function excluirR2020(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id'));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id'));
        $this->getComp($cid);
        $this->eventos->excluir('r2020', $id, $cid);
        $this->redirect("/eventos/r2020?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-2060 ══════════════════════════════════════════════

    public function r2060(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $this->view('pages/eventos/r2060', [
            'pageTitle'   => 'R-2060 – CPRB',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r2060', $cid),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR2060(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $recBruta  = $this->postMoney('valor_rec_bruta');
            $exclusoes = $this->postMoney('valor_exclusoes');
            $aliquota  = $this->postMoney('aliquota');
            $base      = $recBruta - $exclusoes;

            $this->eventos->inserir('r2060', [
                'competencia_id'     => $cid,
                'cnae'               => $this->sanitize($this->post('cnae', '')),
                'valor_rec_bruta'    => $recBruta,
                'valor_exclusoes'    => $exclusoes,
                'valor_base_calculo' => $base,
                'aliquota'           => $aliquota,
                'valor_cprb'         => round($base * ($aliquota / 100), 2),
            ]);
            $this->redirect("/eventos/r2060?competencia_id={$cid}", 'R-2060 salvo!', 'sucesso');
        }, "/eventos/r2060?competencia_id={$cid}");
    }

    public function excluirR2060(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id'));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id'));
        $this->getComp($cid);
        $this->eventos->excluir('r2060', $id, $cid);
        $this->redirect("/eventos/r2060?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-4010 ══════════════════════════════════════════════

    public function r4010(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $this->view('pages/eventos/r4010', [
            'pageTitle'   => 'R-4010 – Pagamentos/Créditos PF (IRRF)',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4010', $cid, 'data_pagamento DESC'),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR4010(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $this->eventos->inserir('r4010', [
                'competencia_id'      => $cid,
                'cpf_beneficiario'    => $this->postCpf('cpf_beneficiario'),
                'nome_beneficiario'   => $this->sanitize($this->post('nome_beneficiario', '')),
                'natureza_rendimento' => $this->post('natureza_rendimento', ''),
                'data_pagamento'      => $this->post('data_pagamento', date('Y-m-d')),
                'valor_bruto'         => $this->postMoney('valor_bruto'),
                'valor_ir'            => $this->postMoney('valor_ir'),
                'valor_base_ir'       => $this->postMoney('valor_base_ir'),
                'valor_deducao'       => $this->postMoney('valor_deducao'),
                'descricao_pagamento' => $this->sanitize($this->post('descricao_pagamento', '')),
            ]);
            $this->redirect("/eventos/r4010?competencia_id={$cid}", 'Pagamento PF adicionado!', 'sucesso');
        }, "/eventos/r4010?competencia_id={$cid}");
    }

    public function excluirR4010(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id'));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id'));
        $this->getComp($cid);
        $this->eventos->excluir('r4010', $id, $cid);
        $this->redirect("/eventos/r4010?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-4020 ══════════════════════════════════════════════

    public function r4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $this->view('pages/eventos/r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4020', $cid, 'data_pagamento DESC'),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $this->eventos->inserir('r4020', [
                'competencia_id'            => $cid,
                'cnpj_beneficiario'         => $this->postCnpj('cnpj_beneficiario'),
                'razao_social_beneficiario' => $this->sanitize($this->post('razao_social_beneficiario', '')),
                'natureza_rendimento'       => $this->post('natureza_rendimento', ''),
                'data_pagamento'            => $this->post('data_pagamento', date('Y-m-d')),
                'valor_bruto'               => $this->postMoney('valor_bruto'),
                'valor_ir'                  => $this->postMoney('valor_ir'),
                'valor_csll'                => $this->postMoney('valor_csll'),
                'valor_cofins'              => $this->postMoney('valor_cofins'),
                'valor_pis'                 => $this->postMoney('valor_pis'),
                'valor_base_ir'             => $this->postMoney('valor_base_ir'),
            ]);
            $this->redirect("/eventos/r4020?competencia_id={$cid}", 'Pagamento PJ adicionado!', 'sucesso');
        }, "/eventos/r4020?competencia_id={$cid}");
    }

    public function excluirR4020(): void
    {
        $this->requireLogin();
        $id  = (int) ($this->post('id') ?? $this->get('id'));
        $cid = (int) ($this->post('competencia_id') ?? $this->get('competencia_id'));
        $this->getComp($cid);
        $this->eventos->excluir('r4020', $id, $cid);
        $this->redirect("/eventos/r4020?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }
}