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
        $tpAmb         = (int) ($this->config['reinf']['tp_amb'] ?? 2);
        $allowSim      = !empty($this->config['security']['allow_simulated_transmission']);
        $isProduction  = ($this->config['app']['env'] ?? '') === 'production' || $tpAmb === 1;

        if (!$temCertValido) {
            if ($isProduction || !$allowSim) {
                $this->redirect(
                    $url,
                    'Certificado A1 válido obrigatório para transmitir. Envio simulado está desabilitado.',
                    'erro'
                );
            }
        }

        $resultado = $temCertValido
            ? $service->enviarLote($comp['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($comp['cnpj'], $xmls);

        foreach ($arquivos as $arq) {
            $this->logs->registrarEnvio($compId, $uid, $arq['evento'] ?? '—', $resultado);
        }

        if ($resultado['sucesso']) {
            $protocolo = $resultado['protocolo'] ?? '';
            $this->arquivos->marcarProtocolo(array_map(fn($a) => (int) $a['id'], $arquivos), $protocolo);

            if (!empty($resultado['simulado'])) {
                // Simulação: NÃO marca competência como transmitido (evita falso compliance)
                $msg = 'Simulação concluída (não oficial). Protocolo fictício: ' . ($protocolo ?: '—')
                     . '. Cadastre um certificado válido para transmissão real.';
                $this->redirect($url, $msg, 'sucesso');
            }

            $this->competencias->marcarTransmitido($compId, $protocolo);
        }

        $msg = ($resultado['sucesso'] ? 'Enviado com sucesso' : 'Falha')
             . '. Protocolo: ' . ($resultado['protocolo'] ?? '—');
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

        $qtdRecibos = 0;
        if ($resultado['sucesso']) {
            $qtdRecibos = $this->arquivos->aplicarRecibos(
                $compId,
                $protocolo,
                $resultado['recibos_por_id'] ?? [],
                $resultado['recibos'] ?? []
            );
        }

        $extra = $qtdRecibos > 0 ? " | {$qtdRecibos} recibo(s) vinculado(s) aos XMLs." : '';
        $this->redirect(
            $url,
            'Retorno: ' . ($resultado['desc_retorno'] ?? '—') . $extra,
            $resultado['sucesso'] ? 'sucesso' : 'erro'
        );
    }
}
