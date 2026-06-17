<?php

namespace App\Controllers;

use App\Services\TransmissaoService;
use App\Services\AssinaturaService;
use App\Models\Database;

class TransmissaoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();

        $competenciaId = (int) $this->get('competencia_id', 0);

        $arquivos    = [];
        $competencia = null;

        if ($competenciaId) {
            $stmt = $this->db->prepare("SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC");
            $stmt->execute([$competenciaId]);
            $arquivos = $stmt->fetchAll();

            $comp = $this->db->prepare("
                SELECT c.*, ct.cnpj, ct.razao_social
                FROM competencias c JOIN contribuintes ct ON ct.id = c.contribuinte_id
                WHERE c.id = ?
            ");
            $comp->execute([$competenciaId]);
            $competencia = $comp->fetch();
        }

        $stmt = $this->db->prepare("
            SELECT t.*, c.periodo, ct.cnpj, ct.razao_social
            FROM transmissoes t
            JOIN competencias c ON c.id = t.competencia_id
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $historico = $stmt->fetchAll();

        $certInfo = (new AssinaturaService())->infoCertificado();

        $this->view('pages/transmissao/index', [
            'pageTitle'      => 'Transmissão SEFAZ',
            'competencia'    => $competencia,
            'arquivos'       => $arquivos,
            'historico'      => $historico,
            'certInfo'       => $certInfo,
            'competenciaId'  => $competenciaId,
        ]);
    }

    public function enviar(): void
    {
        $this->requireLogin();

        $competenciaId = (int) $this->post('competencia_id', 0);
        $arquivoIds    = $_POST['arquivos'] ?? [];

        if (!$competenciaId || empty($arquivoIds)) {
            $this->redirect("/transmissao?competencia_id={$competenciaId}", 'Selecione ao menos um arquivo para enviar.', 'error');
        }

        $comp = $this->db->prepare("
            SELECT c.*, ct.cnpj, ct.razao_social
            FROM competencias c JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE c.id = ?
        ");
        $comp->execute([$competenciaId]);
        $competencia = $comp->fetch();

        if (!$competencia) {
            $this->redirect('/transmissao', 'Competência não encontrada.', 'error');
        }

        // Buscar XMLs
        $placeholders = implode(',', array_fill(0, count($arquivoIds), '?'));
        $stmt = $this->db->prepare("SELECT * FROM arquivos_gerados WHERE id IN ({$placeholders})");
        $stmt->execute($arquivoIds);
        $arquivos = $stmt->fetchAll();

        $xmls = [];
        foreach ($arquivos as $arq) {
            $xml = $arq['xml_conteudo'] ?: (file_exists($arq['caminho']) ? file_get_contents($arq['caminho']) : '');
            if ($xml) {
                $xmls[] = $xml;
            }
        }

        if (empty($xmls)) {
            $this->redirect("/transmissao?competencia_id={$competenciaId}", 'Nenhum XML válido encontrado.', 'error');
        }

        $service = new TransmissaoService($this->db);

        // Verificar certificado — sem cert = modo simulado
        $certInfo  = (new AssinaturaService())->infoCertificado();
        $temCert   = $certInfo['valido'] ?? false;
        $resultado = $temCert
            ? $service->enviarLote($competencia['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($competencia['cnpj'], $xmls);

        // Gravar log
        $usuarioId = $_SESSION['usuario']['id'] ?? 0;
        foreach ($arquivos as $arq) {
            $stmt = $this->db->prepare("
                INSERT INTO transmissoes
                    (competencia_id, usuario_id, tipo_operacao, evento, protocolo,
                     xml_enviado, xml_retorno, codigo_retorno, descricao_retorno,
                     sucesso, tempo_resposta_ms, ambiente)
                VALUES (?, ?, 'envio', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $competenciaId,
                $usuarioId,
                $arq['evento'] ?? '—',
                $resultado['protocolo'] ?? '',
                $resultado['xml_enviado'] ?? '',
                $resultado['xml_retorno'] ?? '',
                $resultado['codigo_retorno'] ?? '',
                $resultado['desc_retorno'] ?? '',
                $resultado['sucesso'] ? 1 : 0,
                $resultado['tempo_ms'] ?? 0,
                $resultado['ambiente'] ?? 2,
            ]);
        }

        if ($resultado['sucesso']) {
            $this->db->prepare("UPDATE competencias SET status = 'transmitido', data_envio = NOW(), num_recibo = ? WHERE id = ?")
                     ->execute([$resultado['protocolo'] ?? '', $competenciaId]);
        }

        $sim = !empty($resultado['simulado']) ? ' (modo simulação)' : '';
        $msg = ($resultado['sucesso'] ? 'Lote enviado com sucesso' : 'Falha no envio') . $sim
             . '. Protocolo: ' . ($resultado['protocolo'] ?? '—');
        $tipo = $resultado['sucesso'] ? 'success' : 'error';

        $this->redirect("/transmissao?competencia_id={$competenciaId}", $msg, $tipo);
    }

    public function consultar(): void
    {
        $this->requireLogin();

        $competenciaId = (int) $this->post('competencia_id', 0);
        $protocolo     = trim($this->post('protocolo', ''));

        if (!$protocolo) {
            $this->redirect("/transmissao?competencia_id={$competenciaId}", 'Informe o número do protocolo.', 'error');
        }

        $comp = $this->db->prepare("
            SELECT c.*, ct.cnpj FROM competencias c
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE c.id = ?
        ");
        $comp->execute([$competenciaId]);
        $competencia = $comp->fetch();

        $service   = new TransmissaoService($this->db);
        $resultado = $service->consultarProtocolo($competencia['cnpj'] ?? '', $protocolo);

        $stmt = $this->db->prepare("
            INSERT INTO transmissoes
                (competencia_id, usuario_id, tipo_operacao, evento, protocolo,
                 xml_retorno, codigo_retorno, descricao_retorno,
                 sucesso, tempo_resposta_ms, ambiente)
            VALUES (?, ?, 'consulta', '', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            $_SESSION['usuario']['id'] ?? 0,
            $protocolo,
            $resultado['xml_retorno'] ?? '',
            $resultado['codigo_retorno'] ?? '',
            $resultado['desc_retorno'] ?? '',
            $resultado['sucesso'] ? 1 : 0,
            $resultado['tempo_ms'] ?? 0,
            $this->config['reinf']['tp_amb'] ?? 2,
        ]);

        $msg = 'Consulta realizada. Retorno: ' . ($resultado['desc_retorno'] ?? '—');
        $this->redirect("/transmissao?competencia_id={$competenciaId}", $msg, $resultado['sucesso'] ? 'success' : 'error');
    }
}