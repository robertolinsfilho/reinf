<?php

namespace App\Controllers;

use App\Models\CompetenciaRepository;
use App\Models\ArquivoGeradoRepository;
use App\Models\TransmissaoLogRepository;
use App\Services\TransmissaoService;
use App\Services\AssinaturaService;

class TransmissaoController extends BaseController
{
    private CompetenciaRepository $competencias;
    private ArquivoGeradoRepository $arquivos;
    private TransmissaoLogRepository $logs;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->competencias = new CompetenciaRepository($this->db);
        $this->arquivos     = new ArquivoGeradoRepository($this->db);
        $this->logs         = new TransmissaoLogRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $compId      = (int) $this->get('competencia_id');
        $competencia = $compId ? $this->competencias->findWithContribuinte($compId, $this->userId()) : null;

        $this->view('pages/transmissao/index', [
            'pageTitle'     => 'Transmissão SEFAZ',
            'competencia'   => $competencia,
            'arquivos'      => $compId ? $this->arquivos->listByCompetencia($compId) : [],
            'historico'     => $this->logs->historico(),
            'certInfo'      => (new AssinaturaService())->infoCertificado(),
            'competenciaId' => $compId,
        ]);
    }

    public function enviar(): void
    {
        $this->requireLogin();
        $compId     = (int) $this->post('competencia_id');
        $arquivoIds = $this->post('arquivos') ?? [];
        $url        = "/transmissao?competencia_id={$compId}";

        if (!$compId || empty($arquivoIds)) {
            $this->redirect($url, 'Selecione ao menos um arquivo.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            $this->redirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $arquivos = $this->arquivos->findByIds($arquivoIds);
        $xmls     = array_filter(array_map(function ($a) {
            return $a['xml_conteudo'] ?: (file_exists($a['caminho']) ? file_get_contents($a['caminho']) : '');
        }, $arquivos));

        if (empty($xmls)) {
            $this->redirect($url, 'Nenhum XML válido.', 'erro');
        }

        $service   = new TransmissaoService($this->db);
        $certInfo  = (new AssinaturaService())->infoCertificado();
        $resultado = ($certInfo['valido'] ?? false)
            ? $service->enviarLote($comp['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($comp['cnpj'], $xmls);

        foreach ($arquivos as $arq) {
            $this->logs->registrarEnvio($compId, $this->userId(), $arq['evento'] ?? '—', $resultado);
        }

        if ($resultado['sucesso']) {
            $this->competencias->marcarTransmitido($compId, $resultado['protocolo'] ?? '');
        }

        $sim  = !empty($resultado['simulado']) ? ' (simulação)' : '';
        $msg  = ($resultado['sucesso'] ? 'Enviado com sucesso' : 'Falha') . $sim . '. Protocolo: ' . ($resultado['protocolo'] ?? '—');
        $this->redirect($url, $msg, $resultado['sucesso'] ? 'sucesso' : 'erro');
    }

    public function consultar(): void
    {
        $this->requireLogin();
        $compId    = (int) $this->post('competencia_id');
        $protocolo = trim($this->post('protocolo', ''));
        $url       = "/transmissao?competencia_id={$compId}";

        if (!$protocolo) {
            $this->redirect($url, 'Informe o protocolo.', 'erro');
        }

        $comp      = $this->competencias->findWithContribuinte($compId, $this->userId());
        $resultado = (new TransmissaoService($this->db))->consultarProtocolo($comp['cnpj'] ?? '', $protocolo);

        $this->logs->registrarConsulta($compId, $this->userId(), $protocolo, $resultado, $this->config['reinf']['tp_amb'] ?? 2);

        $this->redirect($url, 'Retorno: ' . ($resultado['desc_retorno'] ?? '—'), $resultado['sucesso'] ? 'sucesso' : 'erro');
    }
}