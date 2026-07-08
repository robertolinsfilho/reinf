<?php

namespace App\Services;

class TransmissaoService
{
    private array $urlEnvio;
    private array $urlConsulta;
    private int $tpAmb;
    private AssinaturaService $assinatura;

    public function __construct(private \PDO $db)
    {
        $config = \App\Models\AppConfig::get();
        $this->urlEnvio    = $config['reinf']['ws_envio'] ?? [];
        $this->urlConsulta = $config['reinf']['ws_consulta'] ?? [];
        $this->tpAmb       = (int) ($config['reinf']['tp_amb'] ?? 2);
        $this->assinatura  = new AssinaturaService();
    }

    /**
     * Envia lote de XMLs para a Receita.
     */
    public function enviarLote(string $cnpj, array $xmls, bool $assinar = true): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        // Assina cada XML se solicitado
        $eventosAssinados = [];
        foreach ($xmls as $i => $xml) {
            if ($assinar) {
                try {
                    $xml = $this->assinatura->assinar($xml);
                } catch (\Exception $e) {
                    return [
                        'sucesso' => false,
                        'erro'    => "Falha ao assinar evento #{$i}: " . $e->getMessage(),
                    ];
                }
            }
            $eventosAssinados[] = $xml;
        }

        if (count($eventosAssinados) > 50) {
            return ['sucesso' => false, 'erro' => 'Máximo de 50 eventos por lote.'];
        }

        $loteXml = $this->montarLote($cnpj, $eventosAssinados);
        $url     = $this->urlEnvio[$this->tpAmb] ?? '';
        $inicio  = microtime(true);
        $retorno = $this->httpPost($url, $loteXml);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        $sucessoEnvio = in_array($retorno['http_code'], [200, 202]);

        return [
            'sucesso'                  => $sucessoEnvio,
            'assincrono'               => true,
            'aguardando_processamento' => $sucessoEnvio,
            'http_code'                => $retorno['http_code'],
            'xml_enviado'              => $loteXml,
            'xml_retorno'              => $retorno['body'],
            'protocolo'                => $this->extrairTag($retorno['body'], 'nrProtEnvio'),
            'codigo_retorno'           => $this->extrairTag($retorno['body'], 'cdRetorno')
                                          ?: (string) $retorno['http_code'],
            'desc_retorno'             => $this->extrairTag($retorno['body'], 'descRetorno')
                                          ?: ($sucessoEnvio ? 'Lote recebido — aguardando processamento' : $retorno['body']),
            'tempo_ms'                 => $tempo,
            'ambiente'                 => $this->tpAmb,
        ];
    }

    /**
     * Consulta status do lote pelo protocolo.
     */
    public function consultarProtocolo(string $cnpj, string $protocolo): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $url  = ($this->urlConsulta[$this->tpAmb] ?? '') . $protocolo;

        $inicio  = microtime(true);
        $retorno = $this->httpGet($url);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        $sucesso = in_array($retorno['http_code'], [200, 202]);

        return [
            'sucesso'        => $sucesso,
            'http_code'      => $retorno['http_code'],
            'xml_retorno'    => $retorno['body'],
            'codigo_retorno' => $this->extrairTag($retorno['body'], 'cdRetorno')
                                ?: (string) $retorno['http_code'],
            'desc_retorno'   => $this->extrairTag($retorno['body'], 'descRetorno') ?: $retorno['body'],
            'tempo_ms'       => $tempo,
        ];
    }

    /**
     * Envio simulado (sem certificado).
     */
    public function enviarSimulado(string $cnpj, array $xmls): array
    {
        $protocolo = 'SIM' . date('YmdHis') . sprintf('%03d', random_int(0, 999));
        return [
            'sucesso'        => true,
            'simulado'       => true,
            'protocolo'      => $protocolo,
            'codigo_retorno' => '201',
            'desc_retorno'   => 'Lote recebido com sucesso (SIMULAÇÃO — sem certificado).',
            'xml_enviado'    => 'Simulação',
            'xml_retorno'    => "<Retorno><protocolo>{$protocolo}</protocolo></Retorno>",
            'tempo_ms'       => 100,
            'ambiente'       => $this->tpAmb,
        ];
    }

    // ─── Helpers privados ────────────────────────────────────

    private function montarLote(string $cnpj, array $eventosXml): string
    {
        $eventosStr = '';
        foreach ($eventosXml as $i => $xml) {
            $xml = preg_replace('/<\?xml[^?]+\?>\s*/', '', $xml);
            $eventosStr .= "      <evento id=\"evt_{$i}\">\n{$xml}\n      </evento>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<Reinf xmlns="http://www.reinf.esocial.gov.br/schemas/envioLoteEventosAssincrono/v1_00_00"' . "\n"
             . '       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n"
             . "  <envioLoteEventos>\n"
             . "    <ideContribuinte>\n"
             . "      <tpInsc>1</tpInsc>\n"
             . "      <nrInsc>{$cnpj}</nrInsc>\n"
             . "    </ideContribuinte>\n"
             . "    <eventos>\n"
             . $eventosStr
             . "    </eventos>\n"
             . "  </envioLoteEventos>\n"
             . "</Reinf>";
    }

    private function httpPost(string $url, string $xml): array
    {
        if (empty($url)) {
            return ['http_code' => 0, 'body' => 'URL de envio não configurada'];
        }

        $certFiles = $this->extrairCertificadoTemporario();
        if (!$certFiles) {
            return ['http_code' => 0, 'body' => 'Erro: certificado ativo não encontrado ou senha inválida'];
        }

        $userAgent = \App\Models\AppConfig::get()['reinf']['user_agent'] ?? 'EFD-REINF-WEB/1.0';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml',
                'Accept: application/xml',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT        => $certFiles['cert'],
            CURLOPT_SSLKEY         => $certFiles['key'],
            CURLOPT_SSLCERTTYPE    => 'PEM',
            CURLOPT_SSLKEYTYPE     => 'PEM',
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        // Limpa arquivos temporários
        @unlink($certFiles['cert']);
        @unlink($certFiles['key']);

        if ($body === false) {
            return ['http_code' => 0, 'body' => 'Erro cURL: ' . $error];
        }

        return ['http_code' => $httpCode, 'body' => $body];
    }

    private function httpGet(string $url): array
    {
        if (empty($url)) {
            return ['http_code' => 0, 'body' => 'URL de consulta não configurada'];
        }

        $certFiles = $this->extrairCertificadoTemporario();
        if (!$certFiles) {
            return ['http_code' => 0, 'body' => 'Erro: certificado ativo não encontrado'];
        }

        $userAgent = \App\Models\AppConfig::get()['reinf']['user_agent'] ?? 'EFD-REINF-WEB/1.0';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => ['Accept: application/xml'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT        => $certFiles['cert'],
            CURLOPT_SSLKEY         => $certFiles['key'],
            CURLOPT_SSLCERTTYPE    => 'PEM',
            CURLOPT_SSLKEYTYPE     => 'PEM',
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        @unlink($certFiles['cert']);
        @unlink($certFiles['key']);

        if ($body === false) {
            return ['http_code' => 0, 'body' => 'Erro cURL: ' . $error];
        }

        return ['http_code' => $httpCode, 'body' => $body];
    }

    /**
     * Extrai certificado ativo do banco, descriptografa senha e salva
     * PEM + KEY temporários (arquivos são apagados depois do request).
     */
    private function extrairCertificadoTemporario(): ?array
    {
        $repo      = new \App\Models\CertificadoRepository($this->db);
        $certAtivo = $repo->findAtivo();
        if (!$certAtivo || !file_exists($certAtivo['caminho'])) {
            return null;
        }

        // Descriptografa senha
        $senha = '';
        if (!empty($certAtivo['senha_encrypted'])) {
            $config = \App\Models\AppConfig::get();
            $chave  = $config['app']['secret'] ?? 'default_key_change_me_in_production';
            $data   = base64_decode($certAtivo['senha_encrypted']);
            $iv     = substr($data, 0, 16);
            $enc    = substr($data, 16);
            $senha  = openssl_decrypt($enc, 'AES-256-CBC', $chave, 0, $iv) ?: '';
        }

        $pfxContent = file_get_contents($certAtivo['caminho']);
        $certs      = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            return null;
        }

        // Salva PEM (cert público) e KEY (chave privada) em temp
        $tmpDir  = sys_get_temp_dir();
        $certFile = tempnam($tmpDir, 'reinf_cert_') . '.pem';
        $keyFile  = tempnam($tmpDir, 'reinf_key_') . '.pem';

        file_put_contents($certFile, $certs['cert']);
        file_put_contents($keyFile, $certs['pkey']);

        chmod($certFile, 0600);
        chmod($keyFile, 0600);

        return ['cert' => $certFile, 'key' => $keyFile];
    }

    private function extrairTag(string $xml, string $tag): string
    {
        if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $xml, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}