<?php

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
        $cid       = (int) $this->get('competencia_id');
        $naturezas = new NaturezaRendimentoRepository($this->db);

        $this->view('pages/eventos/r4010', [
            'pageTitle'   => 'R-4010 – Pagamentos/Créditos PF (IRRF)',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4010', $cid, 'data_pagamento DESC'),
            'naturezas'   => $naturezas->agrupadoPorTipo('pf'),
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

        // Carrega naturezas da Tabela 4020 (formato PJ oficial)
        $stmt = $this->db->prepare("
            SELECT codigo, descricao, grupo
            FROM naturezas_rendimento
            WHERE ativo = 1 AND tabela_origem = '4020'
            ORDER BY grupo, codigo
        ");
        $stmt->execute();
        $regs = $stmt->fetchAll();

        $naturezas = [];
        foreach ($regs as $r) {
            $naturezas[$r['grupo']][] = ['codigo' => $r['codigo'], 'descricao' => $r['descricao']];
        }

        $this->view('pages/eventos/r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4020', $cid, 'data_pagamento DESC'),
            'naturezas'   => $naturezas,
            'flash'       => $this->getFlash(),
        ]);
    }

    Agora vou passar os arquivos PHP atualizados. São 4:

1. src/Services/ImportacaoService.php — SUBSTITUIR o método importarR4020
Localize o método importarR4020 no arquivo (não substitua o arquivo inteiro, só esse método):
php    private function importarR4020(array $row, int $competenciaId): void
    {
        // Formato oficial (planilha RFB - 22 colunas):
        // A=CNPJ Contribuinte, B=CNPJ Prestador, C=Nº NFS, D=Período Apuração,
        // E=Data Fato Gerador, F=Valor Bruto, G=Cod Tipo Serviço, H=Cód País,
        // I=Base Cálculo, J=IRRF, K=CSRF agregado, L=CSLL, M=PIS, N=COFINS,
        // O=Identificador, P=Ind FCI/SCP, Q=CNPJ FCI/SCP, R=% SCP,
        // S=Ind Judicial, T=Nº Processo, U=Ind Origem, V=Observações

        $cnpjBenef = preg_replace('/\D/', '', (string)($row['B'] ?? ''));

        // Pular linhas em branco
        if (empty($cnpjBenef) || strlen($cnpjBenef) < 11) {
            return;
        }

        $codTipoServico = str_pad(trim((string)($row['G'] ?? '')), 5, '0', STR_PAD_LEFT);
        $natRend        = $codTipoServico; // No R-4020, natureza = cod tipo serviço da Tab 4020

        $stmt = $this->db->prepare("
            INSERT INTO r4020 (
                competencia_id, cnpj_contribuinte, cnpj_beneficiario, num_nfs,
                periodo_apuracao, natureza_rendimento, cod_tipo_servico, cod_pais,
                data_pagamento, valor_bruto, valor_base_ir, valor_ir,
                vl_csrf_agregado, valor_csll, valor_pis, valor_cofins,
                identificador_adicional, indicador_fci_scp, cnpj_fci_scp, percentual_scp,
                indicador_judicial, numero_processo, indicador_origem_recurso, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            preg_replace('/\D/', '', (string)($row['A'] ?? '')) ?: null,
            $cnpjBenef,
            (string)($row['C'] ?? ''),
            $this->parseData($row['D'] ?? null),
            $natRend,
            $codTipoServico,
            (string)($row['H'] ?? '') ?: null,
            $this->parseData($row['E'] ?? null),
            $this->parseMoeda($row['F'] ?? 0),
            $this->parseMoeda($row['I'] ?? 0),
            $this->parseMoeda($row['J'] ?? 0),
            $this->parseMoeda($row['K'] ?? 0),
            $this->parseMoeda($row['L'] ?? 0),
            $this->parseMoeda($row['M'] ?? 0),
            $this->parseMoeda($row['N'] ?? 0),
            (string)($row['O'] ?? '') ?: null,
            !empty($row['P']) ? (int)$row['P'] : null,
            preg_replace('/\D/', '', (string)($row['Q'] ?? '')) ?: null,
            !empty($row['R']) ? (float)$row['R'] : null,
            !empty($row['S']) ? 1 : 0,
            (string)($row['T'] ?? '') ?: null,
            !empty($row['U']) ? (int)$row['U'] : null,
            (string)($row['V'] ?? '') ?: null,
        ]);
    }

2. src/Controllers/EventoController.php — Substituir métodos r4020 e salvarR4020
Substitua os 2 métodos existentes por:
php    // ═══ R-4020 ══════════════════════════════════════════════

    public function r4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->get('competencia_id');

        // Carrega naturezas da Tabela 4020 (formato PJ oficial)
        $stmt = $this->db->prepare("
            SELECT codigo, descricao, grupo
            FROM naturezas_rendimento
            WHERE ativo = 1 AND tabela_origem = '4020'
            ORDER BY grupo, codigo
        ");
        $stmt->execute();
        $regs = $stmt->fetchAll();

        $naturezas = [];
        foreach ($regs as $r) {
            $naturezas[$r['grupo']][] = ['codigo' => $r['codigo'], 'descricao' => $r['descricao']];
        }

        $this->view('pages/eventos/r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ',
            'competencia' => $this->getComp($cid),
            'registros'   => $this->eventos->listar('r4020', $cid, 'data_pagamento DESC'),
            'naturezas'   => $naturezas,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvarR4020(): void
    {
        $this->requireLogin();
        $cid = (int) $this->post('competencia_id');
        $this->getComp($cid);

        $this->safeExecute(function () use ($cid) {
            $codTipoServico = str_pad($this->post('cod_tipo_servico', ''), 5, '0', STR_PAD_LEFT);

            $this->eventos->inserir('r4020', [
                'competencia_id'          => $cid,
                'cnpj_contribuinte'       => $this->postCnpj('cnpj_contribuinte') ?: null,
                'cnpj_beneficiario'       => $this->postCnpj('cnpj_beneficiario'),
                'num_nfs'                 => $this->sanitize($this->post('num_nfs', '')),
                'periodo_apuracao'        => $this->post('periodo_apuracao') ?: null,
                'razao_social_beneficiario' => $this->sanitize($this->post('razao_social_beneficiario', '')),
                'natureza_rendimento'     => $codTipoServico,
                'cod_tipo_servico'        => $codTipoServico,
                'cod_pais'                => $this->post('cod_pais') ?: null,
                'data_pagamento'          => $this->post('data_pagamento', date('Y-m-d')),
                'valor_bruto'             => $this->postMoney('valor_bruto'),
                'valor_base_ir'           => $this->postMoney('valor_base_ir'),
                'valor_ir'                => $this->postMoney('valor_ir'),
                'vl_csrf_agregado'        => $this->postMoney('vl_csrf_agregado'),
                'valor_csll'              => $this->postMoney('valor_csll'),
                'valor_pis'               => $this->postMoney('valor_pis'),
                'valor_cofins'            => $this->postMoney('valor_cofins'),
                'identificador_adicional' => $this->sanitize($this->post('identificador_adicional', '')),
                'indicador_judicial'      => !empty($this->post('indicador_judicial')) ? 1 : 0,
                'numero_processo'         => $this->sanitize($this->post('numero_processo', '')),
                'observacoes'             => $this->sanitize($this->post('observacoes', '')),
            ]);
            $this->redirect("/eventos/r4020?competencia_id={$cid}", 'Pagamento PJ adicionado!', 'sucesso');
        }, "/eventos/r4020?competencia_id={$cid}");
    }

}