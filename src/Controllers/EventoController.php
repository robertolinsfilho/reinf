<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EventoRepository;
use App\Models\CompetenciaRepository;
use App\Models\NaturezaRendimentoRepository;

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
        if (!$id) {
            $stmt = $this->db->prepare("
                SELECT c.id FROM competencias c
                JOIN contribuintes co ON co.id = c.contribuinte_id
                WHERE co.usuario_id = ? AND c.status IN ('aberto', 'fechado')
                ORDER BY c.periodo DESC LIMIT 2
            ");
            $stmt->execute([$this->userId()]);
            $comps = $stmt->fetchAll();

            if (count($comps) === 1) {
                $uri = strtok($_SERVER['REQUEST_URI'], '?');
                $this->redirect("{$uri}?competencia_id={$comps[0]['id']}");
            }
            $this->redirect('/competencias', 'Selecione uma competência.', 'info');
        }
        $comp = $this->competencias->findWithContribuinte($id, $this->userId());
        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }
        return $comp;
    }

    private function paginacao(int $cid, string $tabela): array
    {
        $page   = max(1, (int) $this->get('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;
        $total  = $this->eventos->contar($tabela, $cid);

        return [
            'page'       => $page,
            'limit'      => $limit,
            'offset'     => $offset,
            'total'      => $total,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    private function listarEvento(string $tabela, string $view, string $titulo, string $orderBy = 'created_at DESC', array $extra = []): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $p   = $this->paginacao($cid, $tabela);

        $this->view($view, array_merge([
            'pageTitle'   => $titulo,
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar($tabela, $cid, $orderBy, $p['limit'], $p['offset']),
            'total'       => $p['total'],
            'page'        => $p['page'],
            'totalPages'  => $p['totalPages'],
            'flash'       => $this->getFlash(),
        ], $extra));
    }

    private function excluirEvento(string $tabela, string $path): void
    {
        $this->requireLogin();
        $id  = (int) $this->post('id');
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);
        $this->eventos->excluir($tabela, $id, $cid);
        $this->redirect("{$path}?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-2010 ══════════════════════════════════════════════

    public function r2010(): void
    {
        $this->listarEvento('r2010', 'pages/eventos/r2010', 'R-2010 – Retenções INSS Contratados');
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
                'serie'                  => $this->sanitize($this->post('serie', '0')) ?: '0',
                'num_documento'          => $this->sanitize($this->post('num_documento', '')),
                'data_emissao'           => $this->post('data_emissao'),
                'valor_bruto'            => $this->postMoney('valor_bruto'),
                'valor_base_retencao'    => $this->postMoney('valor_base_retencao') ?: $this->postMoney('valor_bruto'),
                'valor_retencao'         => $this->postMoney('valor_retencao'),
                'valor_desc_senar'       => $this->postMoney('valor_desc_senar'),
                'cod_servico'            => preg_replace('/\D/', '', $this->post('cod_servico', '100000001')) ?: '100000001',
                'ind_cprb'               => in_array($this->post('ind_cprb', '0'), ['0', '1'], true) ? $this->post('ind_cprb', '0') : '0',
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
        $this->excluirEvento('r2010', '/eventos/r2010');
    }

    // ═══ R-2020 ══════════════════════════════════════════════

    public function r2020(): void
    {
        $this->listarEvento('r2020', 'pages/eventos/r2020', 'R-2020 – Retenções INSS Contratantes');
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
        $this->excluirEvento('r2020', '/eventos/r2020');
    }

    // ═══ R-2055 ══════════════════════════════════════════════

    public function r2055(): void
    {
        $this->listarEvento('r2055', 'pages/eventos/r2055', 'R-2055 – Aquisição de Produção Rural');
    }

    public function salvarR2055(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $nrAdq  = preg_replace('/\D/', '', $this->post('nr_insc_adquirente', ''));
            $nrProd = preg_replace('/\D/', '', $this->post('nr_insc_produtor', ''));
            $tpAdq  = $this->post('tp_insc_adquirente', '1');
            $tpProd = $this->post('tp_insc_produtor', strlen($nrProd) <= 11 ? '2' : '1');
            if (!in_array($tpAdq, ['1', '3'], true)) {
                $tpAdq = '1';
            }
            if (!in_array($tpProd, ['1', '2'], true)) {
                $tpProd = '1';
            }

            $indAquis = preg_replace('/\D/', '', $this->post('ind_aquis', ''));
            if ($indAquis === '') {
                $indAquis = $tpProd === '1' ? '3' : '1';
            }

            $indOpc = strtoupper(trim($this->post('ind_opc_cp', '')));
            $indOpc = $indOpc === 'S' ? 'S' : null;

            $this->eventos->inserir('r2055', [
                'competencia_id'     => $cid,
                'tp_insc_adquirente' => $tpAdq,
                'nr_insc_adquirente' => $nrAdq,
                'tp_insc_produtor'   => $tpProd,
                'nr_insc_produtor'   => $nrProd,
                'ind_opc_cp'         => $indOpc,
                'ind_aquis'          => $indAquis,
                'valor_bruto'        => $this->postMoney('valor_bruto'),
                'valor_cp_desc'      => $this->postMoney('valor_cp_desc'),
                'valor_rat_desc'     => $this->postMoney('valor_rat_desc'),
                'valor_senar_desc'   => $this->postMoney('valor_senar_desc'),
            ]);
            $this->redirect("/eventos/r2055?competencia_id={$cid}", 'R-2055 salvo!', 'sucesso');
        }, "/eventos/r2055?competencia_id={$cid}", 'Erro ao salvar R-2055');
    }

    public function excluirR2055(): void
    {
        $this->excluirEvento('r2055', '/eventos/r2055');
    }

    // ═══ R-2060 ══════════════════════════════════════════════

    public function r2060(): void
    {
        $this->listarEvento('r2060', 'pages/eventos/r2060', 'R-2060 – CPRB');
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
        $this->excluirEvento('r2060', '/eventos/r2060');
    }

    // ═══ R-4010 ══════════════════════════════════════════════

    public function r4010(): void
    {
        $naturezas = (new NaturezaRendimentoRepository($this->db))->agrupadoPorTipo('pf');
        $this->listarEvento(
            'r4010',
            'pages/eventos/r4010',
            'R-4010 – Pagamentos/Créditos PF (IRRF)',
            'data_pagamento DESC',
            ['naturezas' => $naturezas]
        );
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
        $this->excluirEvento('r4010', '/eventos/r4010');
    }

    // ═══ R-4020 (Formato Oficial RFB) ═══════════════════════

    public function r4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');
        $p   = $this->paginacao($cid, 'r4020');

        $naturezas = (new NaturezaRendimentoRepository($this->db))->agrupadoPorTipo('pj');

        $editId   = (int) $this->get('id');
        $editando = $editId ? $this->eventos->find('r4020', $editId, $cid) : null;

        $this->view('pages/eventos/r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4020', $cid, 'data_pagamento DESC', $p['limit'], $p['offset']),
            'naturezas'   => $naturezas,
            'editando'    => $editando,
            'total'       => $p['total'],
            'page'        => $p['page'],
            'totalPages'  => $p['totalPages'],
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $id             = (int) $this->post('id', 0);
            $codTipoServico = str_pad((string) $this->post('cod_tipo_servico', ''), 5, '0', STR_PAD_LEFT);
            $indOrig        = $this->post('indicador_origem_recurso') ?: null;
            $indFci         = $this->post('indicador_fci_scp') ?: null;

            $dados = [
                'cnpj_contribuinte'         => $this->postCnpj('cnpj_contribuinte') ?: null,
                'cnpj_beneficiario'         => $this->postCnpj('cnpj_beneficiario'),
                'num_nfs'                   => $this->sanitize($this->post('num_nfs', '')),
                'periodo_apuracao'          => $this->post('periodo_apuracao') ?: null,
                'razao_social_beneficiario' => $this->sanitize($this->post('razao_social_beneficiario', '')),
                'natureza_rendimento'       => $codTipoServico,
                'cod_tipo_servico'          => $codTipoServico,
                'cod_pais'                  => $this->post('cod_pais') ?: null,
                'data_pagamento'            => $this->post('data_pagamento', date('Y-m-d')),
                'valor_bruto'               => $this->postMoney('valor_bruto'),
                'valor_base_ir'             => $this->postMoney('valor_base_ir'),
                'valor_base_csll'           => $this->postMoney('valor_base_csll'),
                'valor_base_cofins'         => $this->postMoney('valor_base_cofins'),
                'valor_base_pis'            => $this->postMoney('valor_base_pis'),
                'valor_base_agreg'          => $this->postMoney('valor_base_agreg'),
                'valor_ir'                  => $this->postMoney('valor_ir'),
                'vl_csrf_agregado'          => $this->postMoney('vl_csrf_agregado'),
                'valor_csll'                => $this->postMoney('valor_csll'),
                'valor_pis'                 => $this->postMoney('valor_pis'),
                'valor_cofins'              => $this->postMoney('valor_cofins'),
                'identificador_adicional'   => $this->sanitize($this->post('identificador_adicional', '')),
                'indicador_fci_scp'         => $indFci !== null && $indFci !== '' ? (int) $indFci : null,
                'cnpj_fci_scp'              => $this->postCnpj('cnpj_fci_scp') ?: null,
                'percentual_scp'            => $this->post('percentual_scp') !== '' && $this->post('percentual_scp') !== null
                    ? (float) str_replace(',', '.', (string) $this->post('percentual_scp'))
                    : null,
                'indicador_judicial'        => !empty($this->post('indicador_judicial')) ? 1 : 0,
                'numero_processo'           => $this->sanitize($this->post('numero_processo', '')),
                'indicador_origem_recurso'  => $indOrig,
                'cnpj_origem_recurso'       => ((string) $indOrig === '2')
                    ? ($this->postCnpj('cnpj_origem_recurso') ?: null)
                    : null,
                'observacoes'               => $this->sanitize($this->post('observacoes', '')),
            ];

            if ($id) {
                $this->eventos->atualizar('r4020', $id, $cid, $dados);
                $msg = 'Pagamento PJ atualizado!';
            } else {
                $this->eventos->inserir('r4020', ['competencia_id' => $cid, ...$dados]);
                $msg = 'Pagamento PJ adicionado!';
            }

            $this->redirect("/eventos/r4020?competencia_id={$cid}", $msg, 'sucesso');
        }, "/eventos/r4020?competencia_id={$cid}");
    }

    public function excluirR4020(): void
    {
        $this->excluirEvento('r4020', '/eventos/r4020');
    }
}
