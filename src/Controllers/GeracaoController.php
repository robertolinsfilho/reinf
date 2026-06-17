<?php

namespace App\Controllers;

use App\Services\GeracaoXmlService;
use App\Services\AssinaturaService;

class GeracaoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();

        $competenciaId = (int) $this->get('competencia_id', 0);
        if (!$competenciaId) {
            $this->redirect('/competencias', 'Selecione uma competência.', 'error');
        }

        $comp = $this->db->prepare("
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
        $eventosDisponiveis = ['R1000' => true];
        $tabelas = ['R2010'=>'r2010','R2020'=>'r2020','R2060'=>'r2060','R4010'=>'r4010','R4020'=>'r4020'];
        foreach ($tabelas as $evento => $tabela) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$tabela} WHERE competencia_id = ?");
            $stmt->execute([$competenciaId]);
            $eventosDisponiveis[$evento] = (int) $stmt->fetchColumn() > 0;
        }

        // Arquivos já gerados
        $stmt = $this->db->prepare("SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC");
        $stmt->execute([$competenciaId]);
        $arquivosGerados = $stmt->fetchAll();

        $certInfo = (new AssinaturaService())->infoCertificado();

        $this->view('pages/geracao/index', [
            'pageTitle'          => 'Gerar XML',
            'competencia'        => $competencia,
            'eventosDisponiveis' => $eventosDisponiveis,
            'arquivosGerados'    => $arquivosGerados,
            'certInfo'           => $certInfo,
        ]);
    }

    public function gerar(): void
    {
        $this->requireLogin();

        $competenciaId       = (int) $this->post('competencia_id', 0);
        $eventosSelecionados = $_POST['eventos'] ?? [];
        $assinarXml          = !empty($_POST['assinar']);

        if (!$competenciaId || empty($eventosSelecionados)) {
            $this->redirect("/gerar?competencia_id={$competenciaId}", 'Selecione ao menos um evento.', 'error');
        }

        $comp = $this->db->prepare("
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
            $service  = new GeracaoXmlService($this->db);
            $arquivos = $service->gerar($competencia, $eventosSelecionados);

            if ($assinarXml) {
                $assinatura = new AssinaturaService();
                foreach ($arquivos as &$arq) {
                    $arq['xml']      = $assinatura->assinar($arq['xml']);
                    file_put_contents($arq['caminho'], $arq['xml']);
                    $arq['hash']     = md5_file($arq['caminho']);
                    $arq['tamanho']  = filesize($arq['caminho']);
                    $arq['assinado'] = true;
                }
                unset($arq);
            }

            // Salvar no banco
            $usuarioId = $_SESSION['usuario']['id'] ?? 0;
            foreach ($arquivos as $arq) {
                $stmt = $this->db->prepare("
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
            $msg = "{$qtd} XML(s) gerado(s)" . ($assinarXml ? ' e assinado(s)' : '') . ' com sucesso!';
            $this->redirect("/gerar?competencia_id={$competenciaId}", $msg, 'success');

        } catch (\Exception $e) {
            $this->redirect("/gerar?competencia_id={$competenciaId}", 'Erro: ' . $e->getMessage(), 'error');
        }
    }

    public function download(): void
    {
        $this->requireLogin();

        $id = (int) $this->get('id', 0);
        $stmt = $this->db->prepare("SELECT * FROM arquivos_gerados WHERE id = ?");
        $stmt->execute([$id]);
        $arquivo = $stmt->fetch();

        if (!$arquivo) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        $conteudo = '';
        if (file_exists($arquivo['caminho'])) {
            $conteudo = file_get_contents($arquivo['caminho']);
        } elseif (!empty($arquivo['xml_conteudo'])) {
            $conteudo = $arquivo['xml_conteudo'];
        }

        if (!$conteudo) {
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