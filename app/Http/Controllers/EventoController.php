<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\CompetenciaRepository;
use App\Repositories\EventoRepository;
use App\Repositories\NaturezaRendimentoRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    private EventoRepository $eventos;
    private CompetenciaRepository $competencias;

    public function __construct()
    {
        $this->eventos      = new EventoRepository();
        $this->competencias = new CompetenciaRepository();
    }

    public function index()
    {
        return $this->render('pages.eventos.index', [
            'pageTitle' => 'Eventos REINF',
        ]);
    }

    /**
     * @return array|RedirectResponse
     */
    private function getComp(Request $request, int $id)
    {
        if (!$id) {
            $ids = $this->competencias->listIdsAbertasOuFechadas($this->userId(), 2);

            if (count($ids) === 1) {
                return redirect('/' . ltrim($request->path(), '/') . "?competencia_id={$ids[0]}");
            }
            return $this->flashRedirect('/competencias', 'Selecione uma competência.', 'info');
        }
        $comp = $this->competencias->findWithContribuinte($id, $this->userId());
        if (!$comp) {
            return $this->flashRedirect('/competencias', 'Competência não encontrada.', 'erro');
        }
        return $comp;
    }

    private function paginacao(Request $request, int $cid, string $tabela): array
    {
        $page   = max(1, (int) $request->query('page', 1));
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

    private function listarEvento(Request $request, string $tabela, string $view, string $titulo, string $orderBy = 'created_at DESC', array $extra = [])
    {
        $cid  = (int) $request->query('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }
        $p = $this->paginacao($request, $cid, $tabela);

        return $this->render($view, array_merge([
            'pageTitle'   => $titulo,
            'competencia' => $comp,
            'registros'   => $this->eventos->listar($tabela, $cid, $orderBy, $p['limit'], $p['offset']),
            'total'       => $p['total'],
            'page'        => $p['page'],
            'totalPages'  => $p['totalPages'],
        ], $extra));
    }

    private function excluirEvento(Request $request, string $tabela, string $path)
    {
        $id   = (int) $request->input('id');
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }
        $this->eventos->excluir($tabela, $id, $cid);
        return $this->flashRedirect("{$path}?competencia_id={$cid}", 'Registro excluído.', 'sucesso');
    }

    // ═══ R-2010 ══════════════════════════════════════════════

    public function r2010(Request $request)
    {
        return $this->listarEvento($request, 'r2010', 'pages.eventos.r2010', 'R-2010 – Retenções INSS Contratados');
    }

    public function salvarR2010(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $id    = (int) $request->input('id', 0);
            $dados = [
                'cnpj_prestador'         => $this->postCnpj($request, 'cnpj_prestador'),
                'razao_social_prestador' => $this->sanitize($request->input('razao_social_prestador', '')),
                'tipo_insc_prestador'    => $request->input('tipo_insc_prestador', '1'),
                'serie'                  => $this->sanitize($request->input('serie', '0')) ?: '0',
                'num_documento'          => $this->sanitize($request->input('num_documento', '')),
                'data_emissao'           => $request->input('data_emissao'),
                'valor_bruto'            => $this->postMoney($request, 'valor_bruto'),
                'valor_base_retencao'    => $this->postMoney($request, 'valor_base_retencao') ?: $this->postMoney($request, 'valor_bruto'),
                'valor_retencao'         => $this->postMoney($request, 'valor_retencao'),
                'valor_desc_senar'       => $this->postMoney($request, 'valor_desc_senar'),
                'cod_servico'            => preg_replace('/\D/', '', (string) $request->input('cod_servico', '100000001')) ?: '100000001',
                'ind_cprb'               => in_array($request->input('ind_cprb', '0'), ['0', '1'], true) ? $request->input('ind_cprb', '0') : '0',
            ];

            if ($id) {
                $this->eventos->atualizar('r2010', $id, $cid, $dados);
            } else {
                $this->eventos->inserir('r2010', ['competencia_id' => $cid, ...$dados]);
            }

            return $this->flashRedirect("/eventos/r2010?competencia_id={$cid}", 'R-2010 salvo!', 'sucesso');
        }, "/eventos/r2010?competencia_id={$cid}", 'Erro ao salvar R-2010');
    }

    public function excluirR2010(Request $request)
    {
        return $this->excluirEvento($request, 'r2010', '/eventos/r2010');
    }

    // ═══ R-2020 ══════════════════════════════════════════════

    public function r2020(Request $request)
    {
        return $this->listarEvento($request, 'r2020', 'pages.eventos.r2020', 'R-2020 – Retenções INSS Contratantes');
    }

    public function salvarR2020(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $this->eventos->inserir('r2020', [
                'competencia_id'       => $cid,
                'cnpj_tomador'         => $this->postCnpj($request, 'cnpj_tomador'),
                'razao_social_tomador' => $this->sanitize($request->input('razao_social_tomador', '')),
                'tipo_insc_tomador'    => $request->input('tipo_insc_tomador', '1'),
                'num_documento'        => $this->sanitize($request->input('num_documento', '')),
                'data_emissao'         => $request->input('data_emissao'),
                'valor_bruto'          => $this->postMoney($request, 'valor_bruto'),
                'valor_retencao'       => $this->postMoney($request, 'valor_retencao'),
            ]);
            return $this->flashRedirect("/eventos/r2020?competencia_id={$cid}", 'R-2020 salvo!', 'sucesso');
        }, "/eventos/r2020?competencia_id={$cid}");
    }

    public function excluirR2020(Request $request)
    {
        return $this->excluirEvento($request, 'r2020', '/eventos/r2020');
    }

    // ═══ R-2055 ══════════════════════════════════════════════

    public function r2055(Request $request)
    {
        return $this->listarEvento($request, 'r2055', 'pages.eventos.r2055', 'R-2055 – Aquisição de Produção Rural');
    }

    public function salvarR2055(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $nrAdq  = preg_replace('/\D/', '', (string) $request->input('nr_insc_adquirente', ''));
            $nrProd = preg_replace('/\D/', '', (string) $request->input('nr_insc_produtor', ''));
            $tpAdq  = $request->input('tp_insc_adquirente', '1');
            $tpProd = $request->input('tp_insc_produtor', strlen($nrProd) <= 11 ? '2' : '1');
            if (!in_array($tpAdq, ['1', '3'], true)) {
                $tpAdq = '1';
            }
            if (!in_array($tpProd, ['1', '2'], true)) {
                $tpProd = '1';
            }

            $indAquis = preg_replace('/\D/', '', (string) $request->input('ind_aquis', ''));
            if ($indAquis === '') {
                $indAquis = $tpProd === '1' ? '3' : '1';
            }

            $indOpc = strtoupper(trim((string) $request->input('ind_opc_cp', '')));
            $indOpc = $indOpc === 'S' ? 'S' : null;

            $this->eventos->inserir('r2055', [
                'competencia_id'     => $cid,
                'tp_insc_adquirente' => $tpAdq,
                'nr_insc_adquirente' => $nrAdq,
                'tp_insc_produtor'   => $tpProd,
                'nr_insc_produtor'   => $nrProd,
                'ind_opc_cp'         => $indOpc,
                'ind_aquis'          => $indAquis,
                'valor_bruto'        => $this->postMoney($request, 'valor_bruto'),
                'valor_cp_desc'      => $this->postMoney($request, 'valor_cp_desc'),
                'valor_rat_desc'     => $this->postMoney($request, 'valor_rat_desc'),
                'valor_senar_desc'   => $this->postMoney($request, 'valor_senar_desc'),
            ]);
            return $this->flashRedirect("/eventos/r2055?competencia_id={$cid}", 'R-2055 salvo!', 'sucesso');
        }, "/eventos/r2055?competencia_id={$cid}", 'Erro ao salvar R-2055');
    }

    public function excluirR2055(Request $request)
    {
        return $this->excluirEvento($request, 'r2055', '/eventos/r2055');
    }

    // ═══ R-2060 ══════════════════════════════════════════════

    public function r2060(Request $request)
    {
        return $this->listarEvento($request, 'r2060', 'pages.eventos.r2060', 'R-2060 – CPRB');
    }

    public function salvarR2060(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $recBruta  = $this->postMoney($request, 'valor_rec_bruta');
            $exclusoes = $this->postMoney($request, 'valor_exclusoes');
            $aliquota  = $this->postMoney($request, 'aliquota');
            $base      = $recBruta - $exclusoes;

            $this->eventos->inserir('r2060', [
                'competencia_id'     => $cid,
                'cnae'               => $this->sanitize($request->input('cnae', '')),
                'valor_rec_bruta'    => $recBruta,
                'valor_exclusoes'    => $exclusoes,
                'valor_base_calculo' => $base,
                'aliquota'           => $aliquota,
                'valor_cprb'         => round($base * ($aliquota / 100), 2),
            ]);
            return $this->flashRedirect("/eventos/r2060?competencia_id={$cid}", 'R-2060 salvo!', 'sucesso');
        }, "/eventos/r2060?competencia_id={$cid}");
    }

    public function excluirR2060(Request $request)
    {
        return $this->excluirEvento($request, 'r2060', '/eventos/r2060');
    }

    // ═══ R-4010 ══════════════════════════════════════════════

    public function r4010(Request $request)
    {
        $naturezas = (new NaturezaRendimentoRepository())->agrupadoPorTipo('pf');
        return $this->listarEvento(
            $request,
            'r4010',
            'pages.eventos.r4010',
            'R-4010 – Pagamentos/Créditos PF (IRRF)',
            'data_pagamento DESC',
            ['naturezas' => $naturezas]
        );
    }

    public function salvarR4010(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $this->eventos->inserir('r4010', [
                'competencia_id'      => $cid,
                'cpf_beneficiario'    => $this->postCpf($request, 'cpf_beneficiario'),
                'nome_beneficiario'   => $this->sanitize($request->input('nome_beneficiario', '')),
                'natureza_rendimento' => $request->input('natureza_rendimento', ''),
                'data_pagamento'      => $request->input('data_pagamento', date('Y-m-d')),
                'valor_bruto'         => $this->postMoney($request, 'valor_bruto'),
                'valor_ir'            => $this->postMoney($request, 'valor_ir'),
                'valor_base_ir'       => $this->postMoney($request, 'valor_base_ir'),
                'valor_deducao'       => $this->postMoney($request, 'valor_deducao'),
                'descricao_pagamento' => $this->sanitize($request->input('descricao_pagamento', '')),
            ]);
            return $this->flashRedirect("/eventos/r4010?competencia_id={$cid}", 'Pagamento PF adicionado!', 'sucesso');
        }, "/eventos/r4010?competencia_id={$cid}");
    }

    public function excluirR4010(Request $request)
    {
        return $this->excluirEvento($request, 'r4010', '/eventos/r4010');
    }

    // ═══ R-4020 (Formato Oficial RFB) ═══════════════════════

    public function r4020(Request $request)
    {
        $cid = (int) $request->query('competencia_id');
        $p   = $this->paginacao($request, $cid, 'r4020');

        $naturezas = (new NaturezaRendimentoRepository())->agrupadoPorTipo('pj');

        $editId   = (int) $request->query('id');
        $editando = $editId ? $this->eventos->find('r4020', $editId, $cid) : null;

        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->render('pages.eventos.r4020', [
            'pageTitle'   => 'R-4020 – Pagamentos/Créditos PJ',
            'competencia' => $comp,
            'registros'   => $this->eventos->listar('r4020', $cid, 'data_pagamento DESC', $p['limit'], $p['offset']),
            'naturezas'   => $naturezas,
            'editando'    => $editando,
            'total'       => $p['total'],
            'page'        => $p['page'],
            'totalPages'  => $p['totalPages'],
        ]);
    }

    public function salvarR4020(Request $request)
    {
        $cid  = (int) $request->input('competencia_id');
        $comp = $this->getComp($request, $cid);
        if ($comp instanceof RedirectResponse) {
            return $comp;
        }

        return $this->safeExecute(function () use ($request, $cid) {
            $id             = (int) $request->input('id', 0);
            $codTipoServico = str_pad((string) $request->input('cod_tipo_servico', ''), 5, '0', STR_PAD_LEFT);
            $indOrig        = $request->input('indicador_origem_recurso') ?: null;
            $indFci         = $request->input('indicador_fci_scp') ?: null;

            $dados = [
                'cnpj_contribuinte'         => $this->postCnpj($request, 'cnpj_contribuinte') ?: null,
                'cnpj_beneficiario'         => $this->postCnpj($request, 'cnpj_beneficiario'),
                'num_nfs'                   => $this->sanitize($request->input('num_nfs', '')),
                'periodo_apuracao'          => $request->input('periodo_apuracao') ?: null,
                'razao_social_beneficiario' => $this->sanitize($request->input('razao_social_beneficiario', '')),
                'natureza_rendimento'       => $codTipoServico,
                'cod_tipo_servico'          => $codTipoServico,
                'cod_pais'                  => $request->input('cod_pais') ?: null,
                'data_pagamento'            => $request->input('data_pagamento', date('Y-m-d')),
                'valor_bruto'               => $this->postMoney($request, 'valor_bruto'),
                'valor_base_ir'             => $this->postMoney($request, 'valor_base_ir'),
                'valor_base_csll'           => $this->postMoney($request, 'valor_base_csll'),
                'valor_base_cofins'         => $this->postMoney($request, 'valor_base_cofins'),
                'valor_base_pis'            => $this->postMoney($request, 'valor_base_pis'),
                'valor_base_agreg'          => $this->postMoney($request, 'valor_base_agreg'),
                'valor_ir'                  => $this->postMoney($request, 'valor_ir'),
                'vl_csrf_agregado'          => $this->postMoney($request, 'vl_csrf_agregado'),
                'valor_csll'                => $this->postMoney($request, 'valor_csll'),
                'valor_pis'                 => $this->postMoney($request, 'valor_pis'),
                'valor_cofins'              => $this->postMoney($request, 'valor_cofins'),
                'identificador_adicional'   => $this->sanitize($request->input('identificador_adicional', '')),
                'indicador_fci_scp'         => $indFci !== null && $indFci !== '' ? (int) $indFci : null,
                'cnpj_fci_scp'              => $this->postCnpj($request, 'cnpj_fci_scp') ?: null,
                'percentual_scp'            => $request->input('percentual_scp') !== '' && $request->input('percentual_scp') !== null
                    ? (float) str_replace(',', '.', (string) $request->input('percentual_scp'))
                    : null,
                'indicador_judicial'        => !empty($request->input('indicador_judicial')) ? 1 : 0,
                'numero_processo'           => $this->sanitize($request->input('numero_processo', '')),
                'indicador_origem_recurso'  => $indOrig,
                'cnpj_origem_recurso'       => ((string) $indOrig === '2')
                    ? ($this->postCnpj($request, 'cnpj_origem_recurso') ?: null)
                    : null,
                'observacoes'               => $this->sanitize($request->input('observacoes', '')),
            ];

            if ($id) {
                $this->eventos->atualizar('r4020', $id, $cid, $dados);
                $msg = 'Pagamento PJ atualizado!';
            } else {
                $this->eventos->inserir('r4020', ['competencia_id' => $cid, ...$dados]);
                $msg = 'Pagamento PJ adicionado!';
            }

            return $this->flashRedirect("/eventos/r4020?competencia_id={$cid}", $msg, 'sucesso');
        }, "/eventos/r4020?competencia_id={$cid}");
    }

    public function excluirR4020(Request $request)
    {
        return $this->excluirEvento($request, 'r4020', '/eventos/r4020');
    }
}
