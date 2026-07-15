<?php

namespace App\Controllers;

use App\Models\CertificadoRepository;
use App\Models\ContribuinteRepository;
use App\Services\CertificadoCrypto;

class CertificadoController extends BaseController
{
    private CertificadoRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo          = new CertificadoRepository($this->db);
        $this->contribuintes = new ContribuinteRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $uid   = $this->userId();
        $certs = $this->repo->listByUser($uid);

        $certAtivo = null;
        foreach ($certs as $c) {
            if ($c['ativo']) {
                $validade = strtotime($c['validade']);
                $diasRest = max(0, (int) ceil(($validade - time()) / 86400));
                $certAtivo = [
                    'valido'    => $validade > time(),
                    'titular'   => $c['titular'] ?? '—',
                    'cnpj'      => $c['cnpj_certificado'] ?? '—',
                    'emissao'   => date('d/m/Y', strtotime($c['created_at'])),
                    'validade'  => date('d/m/Y', $validade),
                    'expirado'  => $validade < time(),
                    'dias_rest' => $diasRest,
                ];
                break;
            }
        }

        $this->view('pages/certificados/index', [
            'pageTitle'     => 'Certificado Digital A1',
            'certificados'  => $certs,
            'certAtivo'     => $certAtivo,
            'contribuintes' => $this->contribuintes->listByUser($uid),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function upload(): void
    {
        $this->requireLogin();
        $uid = $this->userId();

        if (empty($_FILES['certificado']['tmp_name'])) {
            $this->redirect('/certificados', 'Selecione um arquivo PFX/P12.', 'erro');
        }

        $file = $_FILES['certificado'];
        try {
            $this->assertUploadedFile($file, 5 * 1024 * 1024, ['pfx', 'p12']);
        } catch (\RuntimeException $e) {
            $this->redirect('/certificados', $e->getMessage(), 'erro');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $meusContribuintes = $this->contribuintes->listByUser($uid);
        if (empty($meusContribuintes)) {
            $this->redirect('/certificados', 'Cadastre um contribuinte antes de enviar o certificado.', 'erro');
        }

        $contribId = (int) $this->post('contribuinte_id', 0);
        if (!$contribId && count($meusContribuintes) === 1) {
            $contribId = (int) $meusContribuintes[0]['id'];
        }

        if (!$this->contribuintes->findByUser($contribId, $uid)) {
            $this->redirect('/certificados', 'Contribuinte inválido.', 'erro');
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

        $ouField = $certData['subject']['OU'] ?? '';
        if (is_array($ouField)) {
            $ouField = implode(' ', $ouField);
        }
        if (preg_match('/\d{14}/', $ouField, $m)) {
            $cnpjCert = $m[0];
        }

        $destDir = $this->config['reinf']['cert_path'] ?? (BASE_PATH . '/storage/certs/');
        if (!str_starts_with($destDir, '/')) {
            $destDir = BASE_PATH . '/' . ltrim($destDir, './');
        }
        $destDir = rtrim($destDir, '/') . '/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destFile = $destDir . 'cert_u' . $uid . '_' . date('Ymd_His') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $destFile);
        chmod($destFile, 0600);

        $senhaEnc = CertificadoCrypto::encrypt($senha, CertificadoCrypto::secretFromConfig($this->config));

        $this->repo->desativarTodosDoUsuario($uid, $contribId);
        $this->repo->criarComSenha($contribId, $file['name'], $destFile, $senhaEnc, $cnpjCert, $cn, date('Y-m-d', $validTo));

        $this->redirect('/certificados', "Certificado '{$cn}' importado! Válido até " . date('d/m/Y', $validTo), 'sucesso');
    }
}
