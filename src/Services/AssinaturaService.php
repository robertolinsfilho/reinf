<?php

namespace App\Services;

/**
 * Assina XML EFD-Reinf com certificado digital A1 (PFX/P12).
 * Usa XMLDSig (enveloped signature) conforme manual do desenvolvedor.
 */
class AssinaturaService
{
    private string $certPath;
    private string $certPass;

    public function __construct()
    {
        $config = \App\Models\AppConfig::get();
        $this->certPath = $config['reinf']['cert_path'] ?? '';
        $this->certPass = $config['reinf']['cert_pass'] ?? '';
    }

    /**
     * Assina o XML e retorna o XML assinado.
     * @throws \RuntimeException se certificado não encontrado ou inválido
     */
    public function assinar(string $xml, ?string $pfxPath = null, ?string $pfxPass = null): string
    {
        // Se não veio explícito, tenta do banco (certificado ativo)
        if (!$pfxPath || !$pfxPass) {
            $db   = \App\Models\Database::getInstance();
            $repo = new \App\Models\CertificadoRepository($db);
            $certAtivo = $repo->findAtivo();
            if ($certAtivo) {
                $pfxPath = $pfxPath ?: $certAtivo['caminho'];
                if (!$pfxPass && !empty($certAtivo['senha_encrypted'])) {
                    $pfxPass = $this->decryptSenha($certAtivo['senha_encrypted']);
                }
            }
        }

        $pfxPath = $pfxPath ?: $this->findCertificado();
        $pfxPass = $pfxPass ?: $this->certPass;

        if (!$pfxPath || !file_exists($pfxPath)) {
            throw new \RuntimeException("Certificado digital não encontrado em: {$pfxPath}");
        }

        $pfxContent = file_get_contents($pfxPath);
        $certs = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPass)) {
            throw new \RuntimeException("Falha ao ler o certificado PFX. Verifique a senha.");
        }

        $privateKey = $certs['pkey'];
        $publicCert = $certs['cert'];

        $certData = openssl_x509_parse($publicCert);
        $validTo  = $certData['validTo_time_t'] ?? 0;

        if ($validTo < time()) {
            throw new \RuntimeException("Certificado digital expirado em " . date('d/m/Y', $validTo));
        }

        return $this->xmlDsig($xml, $privateKey, $publicCert);
    }

    /**
     * Verifica dados do certificado (para exibição).
     */
    public function infoCertificado(?string $pfxPath = null, ?string $pfxPass = null): array
    {
        // Se não veio explícito, tenta do banco (certificado ativo)
        if (!$pfxPath || !$pfxPass) {
            try {
                $db   = \App\Models\Database::getInstance();
                $repo = new \App\Models\CertificadoRepository($db);
                $certAtivo = $repo->findAtivo();
                if ($certAtivo) {
                    $pfxPath = $pfxPath ?: $certAtivo['caminho'];
                    if (!$pfxPass && !empty($certAtivo['senha_encrypted'])) {
                        $pfxPass = $this->decryptSenha($certAtivo['senha_encrypted']);
                    }
                }
            } catch (\Exception $e) {
                // segue com fallbacks
            }
        }

        $pfxPath = $pfxPath ?: $this->findCertificado();
        $pfxPass = $pfxPass ?: $this->certPass;

        if (!$pfxPath || !file_exists($pfxPath)) {
            return ['valido' => false, 'erro' => 'Certificado não encontrado'];
        }

        $pfxContent = file_get_contents($pfxPath);
        $certs = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPass)) {
            return ['valido' => false, 'erro' => 'Senha inválida ou não armazenada'];
        }

        $data      = openssl_x509_parse($certs['cert']);
        $cn        = $data['subject']['CN'] ?? '—';
        $validFrom = $data['validFrom_time_t'] ?? 0;
        $validTo   = $data['validTo_time_t'] ?? 0;

        $cnpj = '';
        $ouField = $data['subject']['OU'] ?? '';
        if (is_array($ouField)) {
            $ouField = implode(' ', $ouField);
        }
        if (preg_match('/\d{14}/', $ouField, $m)) {
            $cnpj = $m[0];
        }

        return [
            'valido'     => $validTo > time(),
            'titular'    => $cn,
            'cnpj'       => $cnpj,
            'emissao'    => date('d/m/Y', $validFrom),
            'validade'   => date('d/m/Y', $validTo),
            'expirado'   => $validTo < time(),
            'dias_rest'  => max(0, (int) ceil(($validTo - time()) / 86400)),
        ];
    }

    private function decryptSenha(string $encrypted): string
    {
        $config = \App\Models\AppConfig::get();
        $chave  = $config['app']['secret'] ?? 'default_key_change_me_in_production';
        $data   = base64_decode($encrypted);
        $iv     = substr($data, 0, 16);
        $enc    = substr($data, 16);
        return openssl_decrypt($enc, 'AES-256-CBC', $chave, 0, $iv) ?: '';
    }

    private function findCertificado(): ?string
    {
        if (!is_dir($this->certPath)) {
            return null;
        }
        $files = glob($this->certPath . '*.{pfx,p12}', GLOB_BRACE);
        return $files[0] ?? null;
    }

    /**
     * Aplica assinatura XMLDSig (enveloped) ao XML.
     */
    private function xmlDsig(string $xml, $privateKey, $publicCert): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@id]');

        if ($nodes->length === 0) {
            throw new \RuntimeException("XML não contém elemento com atributo 'id' para assinar.");
        }

        $nodeToSign = $nodes->item(0);
        $refUri     = '#' . $nodeToSign->getAttribute('id');

        $canonical = $nodeToSign->C14N(false, false);
        $digestValue = base64_encode(hash('sha256', $canonical, true));

        $signedInfo  = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">';
        $signedInfo .= '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>';
        $signedInfo .= '<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>';
        $signedInfo .= '<Reference URI="' . $refUri . '">';
        $signedInfo .= '<Transforms>';
        $signedInfo .= '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>';
        $signedInfo .= '<Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>';
        $signedInfo .= '</Transforms>';
        $signedInfo .= '<DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>';
        $signedInfo .= '<DigestValue>' . $digestValue . '</DigestValue>';
        $signedInfo .= '</Reference>';
        $signedInfo .= '</SignedInfo>';

        $siDom = new \DOMDocument();
        $siDom->loadXML($signedInfo);
        $siCanonical = $siDom->documentElement->C14N(false, false);

        $signature = '';
        if (!openssl_sign($siCanonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException("Falha ao assinar XML: " . openssl_error_string());
        }
        $signatureValue = base64_encode($signature);

        $x509 = preg_replace('/(-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s)/', '', $publicCert);

        $signatureXml  = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">';
        $signatureXml .= $signedInfo;
        $signatureXml .= '<SignatureValue>' . $signatureValue . '</SignatureValue>';
        $signatureXml .= '<KeyInfo>';
        $signatureXml .= '<X509Data>';
        $signatureXml .= '<X509Certificate>' . $x509 . '</X509Certificate>';
        $signatureXml .= '</X509Data>';
        $signatureXml .= '</KeyInfo>';
        $signatureXml .= '</Signature>';

        $sigFrag = $dom->createDocumentFragment();
        $sigFrag->appendXML($signatureXml);
        $nodeToSign->appendChild($sigFrag);

        return $dom->saveXML();
    }
}