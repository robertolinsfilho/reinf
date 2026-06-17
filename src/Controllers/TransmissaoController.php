<?php

namespace App\Controllers;

use App\Services\TransmissaoService;
use App\Services\AssinaturaService;
use App\Models\Database;

class TransmissaoController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $competenciaId = (int) ($_GET['competencia_id'] ?? 0);
        $db = Database::getInstance();

        if ($competenciaId) {
            // Arquivos prontos para envio
            $stmt = $db->prepare("SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC");
            $stmt->execute([$competenciaId]);
            $arquivos = $stmt->fetchAll();

            $comp = $db->prepare("
                SELECT c.*, ct.cnpj, ct.razao_social
                FROM competencias c JOIN contribuintes ct ON ct.id = c.contribuinte_id
                WHERE c.id = ?
            ");
            $comp->execute([$competenciaId]);
            $competencia = $comp->fetch();
        } else {
            $arquivos    = [];
            $competencia = null;
        }

        // Histórico de transmissões
        $stmt = $db->prepare("
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
            'competencia'   => $competencia,
            'arquivos'      => $arquivos,
            'historico'     => $historico,
            'certInfo'      => $certInfo,
            'competenciaId' => $competenciaId,
        ]);
    }

    public function enviar(): void
    {
        $this->requireAuth();

        $competenciaId = (int) ($_POST['competencia_id'] ?? 0);
        $arquivoIds    = $_POST['arquivos'] ?? [];

        if (!$competenciaId || empty($arquivoIds)) {
            $this->redirect("/transmissao?competencia_id={$competenciaId}", 'Selecione ao menos um arquivo.', 'error');
        }

        $db = Database::getInstance();

        // Buscar dados da competência
        $comp = $db->prepare("
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
        $stmt = $db->prepare("SELECT * FROM arquivos_gerados WHERE id IN ({$placeholders})");
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

        $service = new TransmissaoService($db);

        // Verificar se tem certificado — senão, modo simulado
        $certInfo  = (new AssinaturaService())->infoCertificado();
        $temCert   = $certInfo['valido'] ?? false;
        $resultado = $temCert
            ? $service->enviarLote($competencia['cnpj'], $xmls, assinar: true)
            : $service->enviarSimulado($competencia['cnpj'], $xmls);

        // Gravar log
        $usuarioId = $_SESSION['usuario_id'] ?? 0;
        foreach ($arquivos as $arq) {
            $stmt = $db->prepare("
                INSERT INTO transmissoes
                    (competencia_id, usuario_id, tipo_operacao, evento, protocolo, xml_enviado, xml_retorno, codigo_retorno, descricao_retorno, sucesso, tempo_resposta_ms, ambiente)
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

        // Atualizar status da competência
        if ($resultado['sucesso']) {
            $db->prepare("UPDATE competencias SET status = 'transmitido', data_envio = NOW(), num_recibo = ? WHERE id = ?")
               ->execute([$resultado['protocolo'] ?? '', $competenciaId]);
        }

        $tipo = $resultado['sucesso'] ? 'success' : 'error';
        $sim  = !empty($resultado['simulado']) ? ' (modo simulação)' : '';
        $msg  = ($resultado['sucesso'] ? 'Lote enviado com sucesso' : 'Falha no envio') . $sim
              . '. Protocolo: ' . ($resultado['protocolo'] ?? '—');

        $this->redirect("/transmissao?competencia_id={$competenciaId}", $msg, $tipo);
    }

    public function consultar(): void
    {
        $this->requireAuth();

        $competenciaId = (int) ($_POST['competencia_id'] ?? 0);
        $protocolo     = trim($_POST['protocolo'] ?? '');

        if (!$protocolo) {
            $this->redirect("/transmissao?competencia_id={$competenciaId}", 'Informe o protocolo.', 'error');
        }

        $db = Database::getInstance();
        $comp = $db->prepare("
            SELECT c.*, ct.cnpj FROM competencias c
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE c.id = ?
        ");
        $comp->execute([$competenciaId]);
        $competencia = $comp->fetch();

        $service   = new TransmissaoService($db);
        $resultado = $service->consultarProtocolo($competencia['cnpj'] ?? '', $protocolo);

        // Gravar log
        $stmt = $db->prepare("
            INSERT INTO transmissoes
                (competencia_id, usuario_id, tipo_operacao, evento, protocolo, xml_retorno, codigo_retorno, descricao_retorno, sucesso, tempo_resposta_ms, ambiente)
            VALUES (?, ?, 'consulta', '', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            $_SESSION['usuario_id'] ?? 0,
            $protocolo,
            $resultado['xml_retorno'] ?? '',
            $resultado['codigo_retorno'] ?? '',
            $resultado['desc_retorno'] ?? '',
            $resultado['sucesso'] ? 1 : 0,
            $resultado['tempo_ms'] ?? 0,
            $this->getConfig()['reinf']['tp_amb'] ?? 2,
        ]);

        $msg = 'Consulta realizada. Retorno: ' . ($resultado['desc_retorno'] ?? '—');
        $this->redirect("/transmissao?competencia_id={$competenciaId}", $msg, $resultado['sucesso'] ? 'success' : 'error');
    }

    private function getConfig(): array
    {
        return require BASE_PATH . '/config/app.php';
    }
}