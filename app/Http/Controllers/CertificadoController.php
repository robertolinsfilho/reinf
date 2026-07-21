<?php

namespace App\Http\Controllers;

use App\Repositories\CertificadoRepository;
use App\Repositories\ContribuinteRepository;
use App\Services\CertificadoCrypto;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    private CertificadoRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct()
    {
        $this->repo          = new CertificadoRepository();
        $this->contribuintes = new ContribuinteRepository();
    }

    public function index()
    {
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

        return $this->render('pages.certificados.index', [
            'pageTitle'     => 'Certificado Digital A1',
            'certificados'  => $certs,
            'certAtivo'     => $certAtivo,
            'contribuintes' => $this->contribuintes->listByUser($uid),
        ]);
    }

    public function upload(Request $request)
    {
        $uid = $this->userId();

        $file = $request->file('certificado');
        if (!$file || !$file->isValid()) {
            return $this->flashRedirect('/certificados', 'Selecione um arquivo PFX/P12.', 'erro');
        }

        $fileArr = [
            'error'    => $file->getError(),
            'size'     => $file->getSize(),
            'name'     => $file->getClientOriginalName(),
            'tmp_name' => $file->getPathname(),
        ];
        try {
            $this->assertUploadedFile($fileArr, 5 * 1024 * 1024, ['pfx', 'p12']);
        } catch (\RuntimeException $e) {
            return $this->flashRedirect('/certificados', $e->getMessage(), 'erro');
        }

        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        $meusContribuintes = $this->contribuintes->listByUser($uid);
        if (empty($meusContribuintes)) {
            return $this->flashRedirect('/certificados', 'Cadastre um contribuinte antes de enviar o certificado.', 'erro');
        }

        $contribId = (int) $request->input('contribuinte_id', 0);
        if (!$contribId && count($meusContribuintes) === 1) {
            $contribId = (int) $meusContribuintes[0]['id'];
        }

        if (!$this->contribuintes->findByUser($contribId, $uid)) {
            return $this->flashRedirect('/certificados', 'Contribuinte inválido.', 'erro');
        }

        $senha      = $request->input('senha', '');
        $pfxContent = file_get_contents($file->getPathname());
        $certs      = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            return $this->flashRedirect('/certificados', 'Não foi possível abrir o certificado. Verifique a senha.', 'erro');
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

        $destDir = config('reinf.cert_path', storage_path('certs'));
        if (!str_starts_with($destDir, '/')) {
            $destDir = base_path(ltrim($destDir, './'));
        }
        $destDir = rtrim($destDir, '/') . '/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destName = 'cert_u' . $uid . '_' . date('Ymd_His') . '.' . $ext;
        $destFile = $destDir . $destName;
        $file->move($destDir, $destName);
        chmod($destFile, 0600);

        $senhaEnc = CertificadoCrypto::encrypt($senha, CertificadoCrypto::secretFromConfig());

        $this->repo->desativarTodosDoUsuario($uid, $contribId);
        $this->repo->criarComSenha($contribId, $fileArr['name'], $destFile, $senhaEnc, $cnpjCert, $cn, date('Y-m-d', $validTo));

        return $this->flashRedirect('/certificados', "Certificado '{$cn}' importado! Válido até " . date('d/m/Y', $validTo), 'sucesso');
    }
}
