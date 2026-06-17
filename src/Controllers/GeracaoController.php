<?php

namespace App\Controllers;

use App\Services\GeracaoXmlService;

class GeracaoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        $stmt = $this->db->prepare("
            SELECT c.*, co.razao_social, co.cnpj
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
            ORDER BY c.periodo DESC
        ");
        $stmt->execute([$uid]);
        $competencias = $stmt->fetchAll();

        // Arquivos gerados
        $stmt = $this->db->prepare("
            SELECT ag.*, c.periodo, co.razao_social
            FROM arquivos_gerados ag
            JOIN competencias c ON c.id = ag.competencia_id
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE ag.usuario_id = ?
            ORDER BY ag.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$uid]);
        $arquivos = $stmt->fetchAll();

        $this->view('pages/geracao/index', [
            'pageTitle'   => 'Gerar Arquivo EFD REINF',
            'competencias'=> $competencias,
            'arquivos'    => $arquivos,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function gerarXml(): void
    {
        $this->requireLogin();
        $uid           = $_SESSION['usuario']['id'];
        $competenciaId = (int) $this->post('competencia_id', 0);
        $eventos       = $this->post('eventos', []);

        if (!$competenciaId || empty($eventos)) {
            $this->flash('erro', 'Selecione a competência e pelo menos um evento.');
            $this->redirect('/gerar');
        }

        // Verificar permissão
        $stmt = $this->db->prepare("
            SELECT c.*, co.cnpj, co.razao_social, co.tipo_contribuinte, co.classificacao_tributos
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE c.id = ? AND co.usuario_id = ?
        ");
        $stmt->execute([$competenciaId, $uid]);
        $competencia = $stmt->fetch();

        if (!$competencia) {
            $this->flash('erro', 'Competência não encontrada.');
            $this->redirect('/gerar');
        }

        try {
            $service  = new GeracaoXmlService($this->db);
            $arquivos = $service->gerar($competencia, $eventos);

            foreach ($arquivos as $arqInfo) {
                $stmt = $this->db->prepare("
                    INSERT INTO arquivos_gerados (competencia_id, usuario_id, nome_arquivo, caminho, tamanho, hash_md5)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $competenciaId, $uid,
                    $arqInfo['nome'], $arqInfo['caminho'],
                    $arqInfo['tamanho'], $arqInfo['hash'],
                ]);
            }

            $this->flash('sucesso', count($arquivos) . ' arquivo(s) gerado(s) com sucesso!');
        } catch (\Exception $e) {
            $this->flash('erro', 'Erro ao gerar arquivo: ' . $e->getMessage());
        }

        $this->redirect('/gerar');
    }

    public function download(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];
        $id  = (int) $this->get('id');

        $stmt = $this->db->prepare("SELECT * FROM arquivos_gerados WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $uid]);
        $arquivo = $stmt->fetch();

        if (!$arquivo || !file_exists($arquivo['caminho'])) {
            $this->flash('erro', 'Arquivo não encontrado.');
            $this->redirect('/gerar');
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $arquivo['nome_arquivo'] . '"');
        header('Content-Length: ' . filesize($arquivo['caminho']));
        readfile($arquivo['caminho']);
        exit;
    }
}
