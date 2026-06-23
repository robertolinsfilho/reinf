<?php

namespace App\Controllers;

use App\Models\CompetenciaRepository;
use App\Models\EventoRepository;
use App\Models\ArquivoGeradoRepository;
use App\Services\GeracaoXmlService;
use App\Services\AssinaturaService;

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
            $this->redirect('/competencias', 'Selecione uma competência.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        $disponiveis = ['R1000' => true];
        foreach (['R2010'=>'r2010','R2020'=>'r2020','R2060'=>'r2060','R4010'=>'r4010','R4020'=>'r4020'] as $evt => $tab) {
            $disponiveis[$evt] = $this->eventos->contar($tab, $compId) > 0;
        }

        $this->view('pages/geracao/index', [
            'pageTitle'          => 'Gerar XML',
            'competencia'        => $comp,
            'eventosDisponiveis' => $disponiveis,
            'arquivosGerados'    => $this->arquivos->listByCompetencia($compId),
            'certInfo'           => (new AssinaturaService())->infoCertificado(),
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
        $url          = "/gerar?competencia_id={$compId}";

        if (!$compId || empty($selecionados)) {
            $this->redirect($url, 'Selecione ao menos um evento.', 'erro');
        }

        if ($indRetif === 2 && !$nrRecibo) {
            $this->redirect($url, 'Retificação exige o número do recibo original.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        $this->safeExecute(function () use ($comp, $compId, $selecionados, $assinar, $indRetif, $nrRecibo, $url) {
            $arquivos = (new GeracaoXmlService($this->db))->gerar($comp, $selecionados, $indRetif, $nrRecibo);

            if ($assinar) {
                $assinatura = new AssinaturaService();
                foreach ($arquivos as &$arq) {
                    $arq['xml']     = $assinatura->assinar($arq['xml']);
                    file_put_contents($arq['caminho'], $arq['xml']);
                    $arq['hash']    = md5_file($arq['caminho']);
                    $arq['tamanho'] = filesize($arq['caminho']);
                }
                unset($arq);
            }

            foreach ($arquivos as $arq) {
                $this->arquivos->salvar($compId, $this->userId(), $arq, $assinar, $indRetif, $nrRecibo);
            }

            $tipo = $indRetif === 2 ? 'Retificação' : 'Inclusão';
            $qtd  = count($arquivos);
            $msg  = "{$qtd} XML(s) de {$tipo} gerado(s)" . ($assinar ? ' e assinado(s)' : '') . '!';
            $this->redirect($url, $msg, 'sucesso');
        }, $url, 'Erro na geração');
    }

    public function download(): void
    {
        $this->requireLogin();
        $arquivo = $this->arquivos->find((int) $this->get('id'));

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

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $arquivo['nome_arquivo'] . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
    }
}