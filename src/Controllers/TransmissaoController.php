<?php

namespace App\Controllers;

use App\Models\CompetenciaRepository;
use App\Models\ArquivoGeradoRepository;
use App\Models\TransmissaoLogRepository;
use App\Models\CertificadoRepository;
use App\Services\TransmissaoService;

class TransmissaoController extends BaseController
{
    private CompetenciaRepository $competencias;
    private ArquivoGeradoRepository $arquivos;
    private TransmissaoLogRepository $logs;
    private CertificadoRepository $certificados;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->competencias = new CompetenciaRepository($this->db);
        $this->arquivos     = new ArquivoGeradoRepository($this->db);
        $this->logs         = new TransmissaoLogRepository($this->db);
        $this->certificados = new CertificadoRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $uid         = $this->userId();
        $compId      = (int) $this->get('competencia_id');
        $competencia = $compId ? $this->competencias->findWithContribuinte($compId, $uid) : null;

        $certAtivo = $this->certificados->findAtivoByUser($uid);
        $certInfo  = null;
        if ($certAtivo) {
            $validade = strtotime($certAtivo['validade']);
            $certInfo = [
                'valido'    => $validade > time(),
                'titular'   => $certAtivo['titular'] ?? '—',
                'cnpj'      => $certAtivo['cnpj_certificado'] ?? '—',
                'validade'  => date('d/m/Y', $validade),
                'expirado'  => $validade < time(),
                'dias_rest' => max(0, (int) ceil(($validade - time()) / 86400)),
            ];
        }

        $this->view('pages/transmissao/index', [
            'pageTitle'     => 'Transmissão SEFAZ',
            'competencia'   => $competencia,
            'arquivos'      => $competencia
                ? $this->arquivos->listByCompetenciaForUser((int) $competencia['id'], $uid)
                : [],
            'historico'     => $this->logs->historicoByUser($uid),
            'certInfo'      => $certInfo,
            'competenciaId' => $competencia ? (int) $competencia['id'] : 0,
            'competencias'  => $this->competencias->listByUser($uid),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function enviar(): void
    {
        $this->requireLogin();
        $uid        = $this->userId();
        $compId     = (int) $this->post('competencia_id');
        $arquivoIds = $this->post('arquivos') ?? [];
        $url        = "/transmissao?competencia_id={$compId}";

        if (!$compId || empty($arquivoIds)) {
            $this->redirect($url, 'Selecione ao menos um arquivo.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $uid);
        if (!$comp) {
            $this->redirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $arquivos = $this->arquivos->findByIdsForUser($arquivoIds, $uid);
        $xmls     = array_filter(array_map(function ($a) {
            return $a['xml_conteudo'] ?: (file_exists($a['caminho']) ? file_get_contents($a['caminho']) : '');
        }, $arquivos));

        if (empty($xmls)) {
            $this->redirect($url, 'Nenhum XML válido.', 'erro');
        }

        $service       = new TransmissaoService($this->db, $uid);
        $certAtivo     = $this->certificados->findAtivoByUser($uid);
        $temCertValido = $certAtivo && strtotime($certAtivo['validade']) > time();

        $resultado = $temCertValido
            ? $service->enviarLote($comp['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($comp['cnpj'], $xmls);

        foreach ($arquivos as $arq) {
            $this->logs->registrarEnvio($compId, $uid, $arq['evento'] ?? '—', $resultado);
        }

        if ($resultado['sucesso']) {
            $this->competencias->marcarTransmitido($compId, $resultado['protocolo'] ?? '');
        }

        $sim = !empty($resultado['simulado']) ? ' (simulação)' : '';
        $msg = ($resultado['sucesso'] ? 'Enviado com sucesso' : 'Falha') . $sim . '. Protocolo: ' . ($resultado['protocolo'] ?? '—');
        $this->redirect($url, $msg, $resultado['sucesso'] ? 'sucesso' : 'erro');
    }

    public function consultar(): void
    {
        $this->requireLogin();
        $uid       = $this->userId();
        $compId    = (int) $this->post('competencia_id');
        $protocolo = trim($this->post('protocolo', ''));
        $url       = "/transmissao?competencia_id={$compId}";

        if (!$protocolo) {
            $this->redirect($url, 'Informe o protocolo.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $uid);
        if (!$comp) {
            $this->redirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $resultado = (new TransmissaoService($this->db, $uid))->consultarProtocolo($comp['cnpj'], $protocolo);

        $this->logs->registrarConsulta($compId, $uid, $protocolo, $resultado, $this->config['reinf']['tp_amb'] ?? 2);

        $this->redirect($url, 'Retorno: ' . ($resultado['desc_retorno'] ?? '—'), $resultado['sucesso'] ? 'sucesso' : 'erro');
    }
}
