<?php

namespace App\Http\Controllers;

use App\Repositories\ArquivoGeradoRepository;
use App\Repositories\CertificadoRepository;
use App\Repositories\CompetenciaRepository;
use App\Repositories\TransmissaoLogRepository;
use App\Services\GeracaoXmlService;
use App\Services\TransmissaoService;
use Illuminate\Http\Request;

class TransmissaoController extends Controller
{
    private CompetenciaRepository $competencias;
    private ArquivoGeradoRepository $arquivos;
    private TransmissaoLogRepository $logs;
    private CertificadoRepository $certificados;

    public function __construct()
    {
        parent::__construct();
        $this->competencias = new CompetenciaRepository($this->db);
        $this->arquivos     = new ArquivoGeradoRepository($this->db);
        $this->logs         = new TransmissaoLogRepository($this->db);
        $this->certificados = new CertificadoRepository($this->db);
    }

    public function index(Request $request)
    {
        $uid         = $this->userId();
        $compId      = (int) $request->query('competencia_id');
        $competencia = $compId ? $this->competencias->findWithContribuinte($compId, $uid) : null;

        $certAtivo = null;
        if ($competencia) {
            $certAtivo = $this->certificados->findAtivoByContribuinte(
                (int) $competencia['contribuinte_id'],
                $uid
            );
        }
        if (!$certAtivo) {
            $certAtivo = $this->certificados->findAtivoByUser($uid);
        }
        $certInfo = null;
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

        return $this->render('pages.transmissao.index', [
            'pageTitle'           => 'Transmissão SEFAZ',
            'competencia'         => $competencia,
            'arquivos'            => $competencia
                ? $this->arquivos->listByCompetenciaForUser((int) $competencia['id'], $uid)
                : [],
            'historico'           => $this->logs->historicoByUser($uid),
            'certInfo'            => $certInfo,
            'competenciaId'       => $competencia ? (int) $competencia['id'] : 0,
            'gruposContribuintes' => $competencia
                ? []
                : $this->competencias->listGroupedByContribuinte($uid),
        ]);
    }

    public function enviar(Request $request)
    {
        $uid        = $this->userId();
        $compId     = (int) $request->input('competencia_id');
        $arquivoIds = $request->input('arquivos') ?? [];
        $url        = "/transmissao?competencia_id={$compId}";

        if (!$compId || empty($arquivoIds)) {
            return $this->flashRedirect($url, 'Selecione ao menos um arquivo.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $uid);
        if (!$comp) {
            return $this->flashRedirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $arquivos = $this->arquivos->findByIdsForUser($arquivoIds, $uid);
        $xmls     = array_filter(array_map(function ($a) {
            return $a['xml_conteudo'] ?: (file_exists($a['caminho']) ? file_get_contents($a['caminho']) : '');
        }, $arquivos));

        if (empty($xmls)) {
            return $this->flashRedirect($url, 'Nenhum XML válido.', 'erro');
        }

        $service = new TransmissaoService($this->db, $uid);
        $service->setContribuinteId((int) $comp['contribuinte_id']);
        $certAtivo     = $this->certificados->findAtivoByContribuinte((int) $comp['contribuinte_id'], $uid)
            ?: $this->certificados->findAtivoByUser($uid);
        $temCertValido = $certAtivo && strtotime($certAtivo['validade']) > time();
        $tpAmb         = (int) config('reinf.tp_amb', 2);
        $allowSim      = !empty(config('reinf.security.allow_simulated_transmission'));
        $isProduction  = config('app.env') === 'production' || $tpAmb === 1;

        if (!$temCertValido) {
            if ($isProduction || !$allowSim) {
                return $this->flashRedirect(
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
            $this->arquivos->marcarProtocolo(array_map(fn ($a) => (int) $a['id'], $arquivos), $protocolo);

            if (!empty($resultado['simulado'])) {
                // Simulação: NÃO marca competência como transmitido (evita falso compliance)
                $msg = 'Simulação concluída (não oficial). Protocolo fictício: ' . ($protocolo ?: '—')
                     . '. Cadastre um certificado válido para transmissão real.';
                return $this->flashRedirect($url, $msg, 'sucesso');
            }

            // R-1000/R-1070/R-9000 sozinhos não marcam "transmitido";
            // só quando todos os periódicos com dados forem enviados.
            $eventosLote = array_values(array_map(
                static fn ($a) => (string) ($a['evento'] ?? ''),
                $arquivos
            ));
            $this->competencias->sincronizarStatusTransmissao($compId, $eventosLote, $protocolo);
        }

        $msg = ($resultado['sucesso'] ? 'Enviado com sucesso' : 'Falha')
             . '. ' . ($resultado['desc_retorno'] ?? $resultado['erro'] ?? '')
             . ' Protocolo: ' . ($resultado['protocolo'] ?? '—');
        return $this->flashRedirect($url, trim($msg), $resultado['sucesso'] ? 'sucesso' : 'erro');
    }

    public function consultar(Request $request)
    {
        $uid       = $this->userId();
        $compId    = (int) $request->input('competencia_id');
        $protocolo = trim((string) $request->input('protocolo', ''));
        $url       = "/transmissao?competencia_id={$compId}";

        if (!$protocolo) {
            return $this->flashRedirect($url, 'Informe o protocolo.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $uid);
        if (!$comp) {
            return $this->flashRedirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $svc = new TransmissaoService($this->db, $uid);
        $svc->setContribuinteId((int) $comp['contribuinte_id']);
        $resultado = $svc->consultarProtocolo($comp['cnpj'], $protocolo);

        $this->logs->registrarConsulta($compId, $uid, $protocolo, $resultado, config('reinf.tp_amb', 2));

        $qtdRecibos   = 0;
        $extraLimpeza = '';
        if ($resultado['sucesso']) {
            $qtdRecibos = $this->arquivos->aplicarRecibos(
                $compId,
                $protocolo,
                $resultado['recibos_por_id'] ?? [],
                $resultado['recibos'] ?? []
            );

            // R-9000 aceito: remove localmente o evento excluído + o próprio R-9000
            if ($qtdRecibos > 0 || !empty($resultado['recibos']) || !empty($resultado['recibos_por_id'])) {
                $limpeza = $this->arquivos->limparAposExclusaoR9000($compId, $protocolo, $uid);
                if ($limpeza['originais'] > 0 || $limpeza['r9000'] > 0) {
                    $extraLimpeza = " | Exclusão mútua: {$limpeza['originais']} evento(s) e {$limpeza['r9000']} R-9000 removido(s) localmente.";
                    $this->competencias->reabrirSeSemEnvio($compId);
                }
            }

            // Recalcula status (R-1000 sozinho não deixa "transmitido")
            $this->competencias->sincronizarStatusTransmissao($compId, [], $protocolo);
        }

        $extra = $qtdRecibos > 0 ? " | {$qtdRecibos} recibo(s) vinculado(s) aos XMLs." : '';
        return $this->flashRedirect(
            $url,
            'Retorno: ' . ($resultado['desc_retorno'] ?? '—') . $extra . $extraLimpeza,
            $resultado['sucesso'] ? 'sucesso' : 'erro'
        );
    }

    /**
     * Gera, assina e envia R-9000 para excluir na RFB os eventos selecionados (com recibo).
     * Após consultar o protocolo com sucesso, os XMLs locais são apagados.
     */
    public function excluirRfb(Request $request)
    {
        $uid        = $this->userId();
        $compId     = (int) $request->input('competencia_id');
        $arquivoIds = $request->input('arquivos') ?? [];
        $url        = "/transmissao?competencia_id={$compId}";

        if (!$compId || empty($arquivoIds)) {
            return $this->flashRedirect($url, 'Selecione ao menos um arquivo com recibo para excluir na RFB.', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $uid);
        if (!$comp) {
            return $this->flashRedirect('/transmissao', 'Competência não encontrada.', 'erro');
        }

        $arquivos  = $this->arquivos->findByIdsForUser($arquivoIds, $uid);
        $geracao   = new GeracaoXmlService($this->db);
        $exclusoes = [];

        foreach ($arquivos as $a) {
            if (($a['evento'] ?? '') === 'R9000') {
                continue;
            }
            $recibo = trim((string) ($a['nr_recibo_retornado'] ?? ''));
            $tpEvt  = $geracao->formatarTpEvento((string) ($a['evento'] ?? ''));
            if ($recibo === '' || $tpEvt === '') {
                continue;
            }
            $exclusoes[] = [
                'tp_evento'  => $tpEvt,
                'nr_recibo'  => $recibo,
                'arquivo_id' => (int) $a['id'],
            ];
        }

        if (empty($exclusoes)) {
            return $this->flashRedirect(
                $url,
                'Nenhum arquivo selecionado possui recibo RFB. Consulte o protocolo antes de excluir com R-9000.',
                'erro'
            );
        }

        $certAtivo     = $this->certificados->findAtivoByContribuinte((int) $comp['contribuinte_id'], $uid)
            ?: $this->certificados->findAtivoByUser($uid);
        $temCertValido = $certAtivo && strtotime($certAtivo['validade']) > time();
        $tpAmb         = (int) config('reinf.tp_amb', 2);
        $allowSim      = !empty(config('reinf.security.allow_simulated_transmission'));
        $isProduction  = config('app.env') === 'production' || $tpAmb === 1;

        if (!$temCertValido && ($isProduction || !$allowSim)) {
            return $this->flashRedirect(
                $url,
                'Certificado A1 válido obrigatório para enviar R-9000.',
                'erro'
            );
        }

        try {
            $gerados = $geracao->gerarR9000Exclusoes($comp, $exclusoes);
        } catch (\Throwable $e) {
            return $this->flashRedirect($url, 'Falha ao gerar R-9000: ' . $e->getMessage(), 'erro');
        }

        $idsR9000 = [];
        $xmls     = [];
        foreach ($gerados as $arq) {
            $idsR9000[] = $this->arquivos->salvar($compId, $uid, $arq, false, 1, $arq['nr_recibo_original'] ?? null);
            $xmls[]     = $arq['xml'];
        }

        $service = new TransmissaoService($this->db, $uid);
        $service->setContribuinteId((int) $comp['contribuinte_id']);
        $resultado = $temCertValido
            ? $service->enviarLote($comp['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($comp['cnpj'], $xmls);

        $this->logs->registrarEnvio($compId, $uid, 'R9000', $resultado);

        if (!$resultado['sucesso']) {
            return $this->flashRedirect(
                $url,
                'Falha no envio do R-9000: ' . ($resultado['desc_retorno'] ?? $resultado['erro'] ?? '—'),
                'erro'
            );
        }

        $protocolo = (string) ($resultado['protocolo'] ?? '');
        if ($protocolo !== '') {
            $this->arquivos->marcarProtocolo($idsR9000, $protocolo);
        }

        if (!empty($resultado['simulado'])) {
            // Simulação: limpa localmente (RFB não recebeu nada oficial)
            $limpeza = $this->arquivos->limparAposExclusaoR9000($compId, $protocolo, $uid);
            $this->competencias->reabrirSeSemEnvio($compId);
            return $this->flashRedirect(
                $url,
                "Simulação R-9000 ok. Removidos localmente: {$limpeza['originais']} evento(s) + {$limpeza['r9000']} R-9000. "
                . 'Com certificado, a exclusão oficial exige consultar o protocolo após o envio.',
                'sucesso'
            );
        }

        return $this->flashRedirect(
            $url,
            'R-9000 enviado. Protocolo: ' . ($protocolo ?: '—')
            . '. Consulte o protocolo abaixo — ao aceitar, o sistema apaga o evento local e o R-9000.',
            'sucesso'
        );
    }

    /**
     * Apaga XMLs gerados localmente (não exclui na RFB).
     */
    public function excluirArquivos(Request $request)
    {
        $uid        = $this->userId();
        $compId     = (int) $request->input('competencia_id');
        $arquivoIds = $request->input('arquivos') ?? [];
        $voltar     = trim((string) $request->input('voltar', ''));
        if ($voltar === 'gerar' && $compId) {
            $url = "/gerar?competencia_id={$compId}";
        } else {
            $url = $compId ? "/transmissao?competencia_id={$compId}" : '/transmissao';
        }

        if (empty($arquivoIds)) {
            return $this->flashRedirect($url, 'Selecione ao menos um arquivo para apagar.', 'erro');
        }

        if ($compId) {
            $comp = $this->competencias->findWithContribuinte($compId, $uid);
            if (!$comp) {
                return $this->flashRedirect('/transmissao', 'Competência não encontrada.', 'erro');
            }
        }

        $result = $this->arquivos->excluirForUser($arquivoIds, $uid);
        foreach ($result['competencia_ids'] as $cid) {
            $this->competencias->reabrirSeSemEnvio((int) $cid);
        }

        if ($result['excluidos'] === 0) {
            return $this->flashRedirect($url, 'Nenhum arquivo encontrado para apagar.', 'erro');
        }

        $msg = "{$result['excluidos']} arquivo(s) apagado(s) localmente.";
        if ($result['com_recibo'] > 0) {
            $msg .= " Atenção: {$result['com_recibo']} tinha recibo na RFB — a exclusão oficial exige R-9000.";
        }
        return $this->flashRedirect($url, $msg, 'sucesso');
    }

    /** Apaga linhas do histórico de transmissão (local). */
    public function excluirHistorico(Request $request)
    {
        $uid    = $this->userId();
        $ids    = $request->input('historico') ?? [];
        $compId = (int) $request->input('competencia_id', 0);
        $url    = $compId ? "/transmissao?competencia_id={$compId}" : '/transmissao';

        if (empty($ids)) {
            return $this->flashRedirect($url, 'Selecione ao menos um registro do histórico.', 'erro');
        }

        $qtd = $this->logs->excluirForUser($ids, $uid);
        return $this->flashRedirect(
            $url,
            $qtd > 0 ? "{$qtd} registro(s) do histórico apagado(s)." : 'Nenhum registro apagado.',
            $qtd > 0 ? 'sucesso' : 'erro'
        );
    }
}
