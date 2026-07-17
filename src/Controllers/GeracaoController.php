<?php

namespace App\Controllers;

use App\Models\CompetenciaRepository;
use App\Models\EventoRepository;
use App\Models\ArquivoGeradoRepository;
use App\Services\GeracaoXmlService;
use App\Services\AssinaturaService;
use App\Services\ValidacaoXmlService;

class GeracaoController extends BaseController
{
    private CompetenciaRepository $competencias;
    private EventoRepository $eventos;
    private ArquivoGeradoRepository $arquivos;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->competencias = new CompetenciaRepository($this->db);
        $this->eventos      = new EventoRepository($this->db);
        $this->arquivos     = new ArquivoGeradoRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $compId = (int) $this->get('competencia_id');

        if (!$compId) {
            $this->view('pages/geracao/index', [
                'pageTitle'           => 'Gerar XML',
                'competencia'         => null,
                'gruposContribuintes' => $this->competencias->listGroupedByContribuinte($this->userId()),
                'eventosDisponiveis'  => [],
                'arquivosGerados'     => [],
                'recibosSalvos'       => [],
                'certInfo'            => (new AssinaturaService($this->userId()))->infoCertificado(),
                'flash'               => $this->getFlash(),
            ]);
            return;
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            $this->redirect('/gerar', 'Competência não encontrada.', 'erro');
        }

        $disponiveis = ['R1000' => true, 'R1070' => true];
        foreach (['R2010'=>'r2010','R2020'=>'r2020','R2055'=>'r2055','R2060'=>'r2060','R4010'=>'r4010','R4020'=>'r4020'] as $evt => $tab) {
            $disponiveis[$evt] = $this->eventos->contar($tab, $compId) > 0;
        }

        $this->view('pages/geracao/index', [
            'pageTitle'          => 'Gerar XML',
            'competencia'        => $comp,
            'gruposContribuintes'=> [],
            'eventosDisponiveis' => $disponiveis,
            'arquivosGerados'    => $this->arquivos->listByCompetenciaForUser($compId, $this->userId()),
            'recibosSalvos'      => $this->arquivos->listRecibos($compId, null, $this->userId()),
            'certInfo'           => (new AssinaturaService($this->userId(), (int) $comp['contribuinte_id']))->infoCertificado(),
            'flash'              => $this->getFlash(),
        ]);
    }

    public function gerar(): void
    {
        $this->requireLogin();
        $compId       = (int) $this->post('competencia_id');
        $selecionados = $this->post('eventos') ?? [];
        $assinar      = !empty($this->post('assinar'));
        $indRetif     = (int) ($this->post('ind_retif') ?: 1);
        $nrRecibo     = trim($this->post('nr_recibo_original', '')) ?: null;
        $forcar       = !empty($this->post('forcar'));
        $url          = "/gerar?competencia_id={$compId}";

        if (!$compId || empty($selecionados)) {
            $this->redirect($url, 'Selecione ao menos um evento.', 'erro');
        }

        if ($nrRecibo !== null && !preg_match('/^[A-Za-z0-9.\\-\/]{1,52}$/', $nrRecibo)) {
            $this->redirect($url, 'Número de recibo inválido.', 'erro');
        }

        $recibosSalvos = [];
        $uid = $this->userId();
        foreach (['R4020', 'R2010', 'R2055', 'R2020', 'R2060', 'R4010'] as $evt) {
            if (in_array($evt, $selecionados, true)) {
                $recibosSalvos = array_merge($recibosSalvos, $this->arquivos->listRecibos($compId, $evt, $uid));
            }
        }

        if ($indRetif === 2 && !$nrRecibo && empty($recibosSalvos)) {
            $this->redirect($url, 'Retificação exige o número do recibo original (informe ou consulte o protocolo antes).', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        $this->safeExecute(function () use ($comp, $compId, $selecionados, $assinar, $indRetif, $nrRecibo, $forcar, $url) {
            $arquivos = (new GeracaoXmlService($this->db))->gerar($comp, $selecionados, $indRetif, $nrRecibo);

            // Validar contra XSD
            $validador = new ValidacaoXmlService();
            $validacao = $validador->validarLote(array_map(fn($a) => [
                'evento' => $a['evento'],
                'xml'    => $a['xml'],
                'nome'   => $a['nome'],
            ], $arquivos));

            // Se houver erro de validação e não estiver em modo "forçar", mostra tela de validação
            if (!$validacao['todos_validos'] && !$forcar) {
                $_SESSION['validacao_pendente'] = [
                    'competencia_id'     => $compId,
                    'eventos'            => $selecionados,
                    'assinar'            => $assinar,
                    'ind_retif'          => $indRetif,
                    'nr_recibo_original' => $nrRecibo,
                    'resultado'          => $validacao,
                ];
                $this->redirect("/gerar/validar?competencia_id={$compId}");
            }

            // Assinar se solicitado
            if ($assinar) {
                $assinatura = new AssinaturaService($this->userId(), (int) ($comp['contribuinte_id'] ?? 0) ?: null);
                foreach ($arquivos as &$arq) {
                    $arq['xml']     = $assinatura->assinar($arq['xml']);
                    file_put_contents($arq['caminho'], $arq['xml']);
                    $arq['hash']    = md5_file($arq['caminho']);
                    $arq['tamanho'] = filesize($arq['caminho']);
                }
                unset($arq);
            }

            // Salvar no banco
            foreach ($arquivos as $arq) {
                $this->arquivos->salvar($compId, $this->userId(), $arq, $assinar, $indRetif, $nrRecibo);
            }

            unset($_SESSION['validacao_pendente']);

            $tipo  = $indRetif === 2 ? 'Retificação' : 'Inclusão';
            $qtd   = count($arquivos);
            $aviso = $validacao['tem_aviso'] ? ' (validação parcial — alguns XSDs não instalados)' : '';
            $msg   = "{$qtd} XML(s) de {$tipo} gerado(s)" . ($assinar ? ' e assinado(s)' : '') . "{$aviso}!";
            $this->redirect($url, $msg, 'sucesso');
        }, $url, 'Erro na geração');
    }

    /**
     * Tela de revisão quando a validação XSD falha.
     */
    public function validar(): void
    {
        $this->requireLogin();
        $compId   = (int) $this->get('competencia_id');
        $pendente = $_SESSION['validacao_pendente'] ?? null;

        if (!$pendente) {
            $this->redirect("/gerar?competencia_id={$compId}", 'Nenhuma validação pendente.', 'erro');
        }

        $this->view('pages/geracao/validar', [
            'pageTitle'     => 'Validação XSD',
            'resultado'     => $pendente['resultado'],
            'pendente'      => $pendente,
            'competenciaId' => $compId,
        ]);
    }

    /**
     * Tela de status dos XSDs instalados.
     */
    public function statusXsd(): void
    {
        $this->requireLogin();
        $validador = new ValidacaoXmlService();
        $this->view('pages/geracao/xsd', [
            'pageTitle' => 'Status dos Schemas XSD',
            'status'    => $validador->statusXsds(),
        ]);
    }

    public function download(): void
    {
        $this->requireLogin();
        $arquivo = $this->arquivos->findForUser((int) $this->get('id'), $this->userId());

        if (!$arquivo) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        $conteudo = file_exists($arquivo['caminho'])
            ? file_get_contents($arquivo['caminho'])
            : ($arquivo['xml_conteudo'] ?? '');

        if (!$conteudo) {
            http_response_code(404);
            echo 'XML não disponível.';
            return;
        }

        $nome = basename((string) $arquivo['nome_arquivo']);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
    }
}