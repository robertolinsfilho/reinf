<?php

namespace App\Controllers;

use App\Services\AssinaturaService;
use App\Models\Database;

class CertificadoController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM certificados ORDER BY created_at DESC");
        $stmt->execute();
        $certs = $stmt->fetchAll();

        $certAtivo = null;
        foreach ($certs as $c) {
            if ($c['ativo']) {
                $certAtivo = (new AssinaturaService())->infoCertificado(
                    $c['caminho'],
                    '' // senha seria armazenada de forma segura
                );
                break;
            }
        }

        $this->view('pages/certificados/index', [
            'certificados' => $certs,
            'certAtivo'    => $certAtivo,
        ]);
    }

    public function upload(): void
    {
        $this->requireAuth();

        if (empty($_FILES['certificado']['tmp_name'])) {
            $this->redirect('/certificados', 'Selecione um arquivo PFX/P12.', 'error');
        }

        $file = $_FILES['certificado'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pfx', 'p12'])) {
            $this->redirect('/certificados', 'Apenas arquivos .pfx ou .p12 são aceitos.', 'error');
        }

        $senha = $_POST['senha'] ?? '';

        // Validar se abre
        $pfxContent = file_get_contents($file['tmp_name']);
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            $this->redirect('/certificados', 'Não foi possível abrir o certificado. Verifique a senha.', 'error');
        }

        $certData = openssl_x509_parse($certs['cert']);
        $cn       = $certData['subject']['CN'] ?? 'Desconhecido';
        $validTo  = $certData['validTo_time_t'] ?? 0;

        // Extrair CNPJ
        $cnpjCert = '';
        if (preg_match('/\d{14}/', $certData['subject']['OU'] ?? '', $m)) {
            $cnpjCert = $m[0];
        }

        // Salvar arquivo
        $config  = require BASE_PATH . '/config/app.php';
        $destDir = $config['reinf']['cert_path'];
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destFile = $destDir . 'cert_' . date('Ymd_His') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $destFile);

        // Desativar anteriores e gravar
        $db = Database::getInstance();
        $contribuinteId = (int) ($_POST['contribuinte_id'] ?? ($_SESSION['contribuinte_id'] ?? 1));

        $db->prepare("UPDATE certificados SET ativo = 0 WHERE contribuinte_id = ?")->execute([$contribuinteId]);

        $stmt = $db->prepare("
            INSERT INTO certificados (contribuinte_id, nome_arquivo, caminho, cnpj_certificado, titular, validade, ativo)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $contribuinteId,
            $file['name'],
            $destFile,
            $cnpjCert,
            $cn,
            date('Y-m-d', $validTo),
        ]);

        $this->redirect('/certificados', "Certificado '{$cn}' importado com sucesso! Válido até " . date('d/m/Y', $validTo), 'success');
    }
}