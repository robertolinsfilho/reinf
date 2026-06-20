<?php

namespace App\Controllers;

use App\Models\CertificadoRepository;
use App\Services\AssinaturaService;

class CertificadoController extends BaseController
{
    private CertificadoRepository $repo;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo = new CertificadoRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $certs = $this->repo->listAll();

        $certAtivo = null;
        foreach ($certs as $c) {
            if ($c['ativo']) {
                $certAtivo = (new AssinaturaService())->infoCertificado($c['caminho'], '');
                break;
            }
        }

        $this->view('pages/certificados/index', [
            'pageTitle'    => 'Certificado Digital A1',
            'certificados' => $certs,
            'certAtivo'    => $certAtivo,
        ]);
    }

    public function upload(): void
    {
        $this->requireLogin();

        if (empty($_FILES['certificado']['tmp_name'])) {
            $this->redirect('/certificados', 'Selecione um arquivo PFX/P12.', 'erro');
        }

        $file = $_FILES['certificado'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pfx', 'p12'])) {
            $this->redirect('/certificados', 'Apenas .pfx ou .p12.', 'erro');
        }

        $senha      = $this->post('senha', '');
        $pfxContent = file_get_contents($file['tmp_name']);
        $certs      = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            $this->redirect('/certificados', 'Não foi possível abrir o certificado. Verifique a senha.', 'erro');
        }

        $certData = openssl_x509_parse($certs['cert']);
        $cn       = $certData['subject']['CN'] ?? 'Desconhecido';
        $validTo  = $certData['validTo_time_t'] ?? 0;
        $cnpjCert = '';

        if (preg_match('/\d{14}/', $certData['subject']['OU'] ?? '', $m)) {
            $cnpjCert = $m[0];
        }

        $destDir = $this->config['reinf']['cert_path'] ?? BASE_PATH . '/storage/certs/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destFile = $destDir . 'cert_' . date('Ymd_His') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $destFile);

        $contribId = (int) $this->post('contribuinte_id', 1);
        $this->repo->desativarTodos($contribId);
        $this->repo->criar($contribId, $file['name'], $destFile, $cnpjCert, $cn, date('Y-m-d', $validTo));

        $this->redirect('/certificados', "Certificado '{$cn}' importado! Válido até " . date('d/m/Y', $validTo), 'sucesso');
    }
}