<?php

namespace App\Controllers;

use App\Services\GeracaoXmlService;
use App\Services\AssinaturaService;
use App\Models\Database;

class GeracaoController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $competenciaId = (int) ($_GET['competencia_id'] ?? 0);
        if (!$competenciaId) {
            $this->redirect('/competencias', 'Selecione uma competência.', 'error');
        }

        $db   = Database::getInstance();
        $comp = $db->prepare("
            SELECT c.*, ct.cnpj, ct.razao_social, ct.classificacao_tributos
            FROM competencias c
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE c.id = ?
        ");
        $comp->execute([$competenciaId]);
        $competencia = $comp->fetch();

        if (!$competencia) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'error');
        }

        // Verificar quais eventos têm dados
        $eventosDisponiveis = [];
        $tabelas = [
            'R1000' => null, // sempre disponível
            'R2010' => 'r2010',
            'R2020' => 'r2020',
            'R2060' => 'r2060',
            'R4010' => 'r4010',
            'R4020' => 'r4020',
        ];

        foreach ($tabelas as $evento => $tabela) {
            if ($tabela === null) {
                $eventosDisponiveis[$evento] = true;
                continue;
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$tabela} WHERE competencia_id = ?");
            $stmt->execute([$competenciaId]);
            $eventosDisponiveis[$evento] = (int) $stmt->fetchColumn() > 0;
        }

        // Arquivos já gerados
        $stmt = $db->prepare("SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC");
        $stmt->execute([$competenciaId]);
        $arquivosGerados = $stmt->fetchAll();

        // Info do certificado
        $certInfo = (new AssinaturaService())->infoCertificado();

        $this->view('pages/geracao/index', [
            'competencia'        => $competencia,
            'eventosDisponiveis' => $eventosDisponiveis,
            'arquivosGerados'    => $arquivosGerados,
            'certInfo'           => $certInfo,
        ]);
    }

    public function gerar(): void
    {
        $this->requireAuth();

        $competenciaId  = (int) ($_POST['competencia_id'] ?? 0);
        $eventosSelecionados = $_POST['eventos'] ?? [];
        $assinarXml     = !empty($_POST['assinar']);

        if (!$competenciaId || empty($eventosSelecionados)) {
            $this->redirect("/geracao?competencia_id={$competenciaId}", 'Selecione ao menos um evento.', 'error');
        }

        $db   = Database::getInstance();
        $comp = $db->prepare("
            SELECT c.*, ct.cnpj, ct.razao_social, ct.classificacao_tributos
            FROM competencias c
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE c.id = ?
        ");
        $comp->execute([$competenciaId]);
        $competencia = $comp->fetch();

        if (!$competencia) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'error');
        }

        try {
            $service  = new GeracaoXmlService($db);
            $arquivos = $service->gerar($competencia, $eventosSelecionados);

            // Assinar se solicitado
            if ($assinarXml) {
                $assinatura = new AssinaturaService();
                foreach ($arquivos as &$arq) {
                    $arq['xml'] = $assinatura->assinar($arq['xml']);
                    file_put_contents($arq['caminho'], $arq['xml']);
                    $arq['hash']     = md5_file($arq['caminho']);
                    $arq['tamanho']  = filesize($arq['caminho']);
                    $arq['assinado'] = true;
                }
                unset($arq);
            }

            // Salvar no banco
            $usuarioId = $_SESSION['usuario_id'] ?? 0;
            foreach ($arquivos as $arq) {
                $stmt = $db->prepare("
                    INSERT INTO arquivos_gerados
                        (competencia_id, usuario_id, evento, nome_arquivo, caminho, tamanho, hash_md5, xml_conteudo, assinado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $competenciaId,
                    $usuarioId,
                    $arq['evento'],
                    $arq['nome'],
                    $arq['caminho'],
                    $arq['tamanho'],
                    $arq['hash'],
                    $arq['xml'],
                    $assinarXml ? 1 : 0,
                ]);
            }

            $qtd = count($arquivos);
            $msg = "{$qtd} XML(s) gerado(s) com sucesso" . ($assinarXml ? ' e assinado(s)' : '') . '.';
            $this->redirect("/geracao?competencia_id={$competenciaId}", $msg, 'success');

        } catch (\Exception $e) {
            $this->redirect("/geracao?competencia_id={$competenciaId}", 'Erro: ' . $e->getMessage(), 'error');
        }
    }

    public function download(): void
    {
        $this->requireAuth();

        $id = (int) ($_GET['id'] ?? 0);
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT * FROM arquivos_gerados WHERE id = ?");
        $stmt->execute([$id]);
        $arquivo = $stmt->fetch();

        if (!$arquivo) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        // Usar conteúdo do banco se o arquivo não existir no disco
        if (file_exists($arquivo['caminho'])) {
            $conteudo = file_get_contents($arquivo['caminho']);
        } elseif (!empty($arquivo['xml_conteudo'])) {
            $conteudo = $arquivo['xml_conteudo'];
        } else {
            http_response_code(404);
            echo 'Conteúdo do XML não disponível.';
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $arquivo['nome_arquivo'] . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
    }
}