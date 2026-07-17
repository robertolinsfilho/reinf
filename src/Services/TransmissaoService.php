<?php

declare(strict_types=1);

namespace App\Services;

class TransmissaoService
{
    private array $urlEnvio;
    private array $urlConsulta;
    private int $tpAmb;
    private AssinaturaService $assinatura;
    private ?int $contribuinteId = null;

    public function __construct(private \PDO $db, private ?int $userId = null)
    {
        $config = \App\Models\AppConfig::get();
        $this->urlEnvio    = $config['reinf']['ws_envio'] ?? [];
        $this->urlConsulta = $config['reinf']['ws_consulta'] ?? [];
        $this->tpAmb       = (int) ($config['reinf']['tp_amb'] ?? 2);
        $this->assinatura  = new AssinaturaService($userId);
    }

    public function setContribuinteId(?int $contribuinteId): void
    {
        $this->contribuinteId = $contribuinteId;
        $this->assinatura->setContribuinteId($contribuinteId);
    }

    /**
     * Envia lote de XMLs para a Receita.
     */
    public function enviarLote(string $cnpj, array $xmls, bool $assinar = true): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

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
        $retorno = $this->httpRequest('POST', $url, $loteXml);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        $sucessoEnvio = in_array($retorno['http_code'], [200, 201, 202], true);
        $descRetorno  = $this->extrairOcorrencias($retorno['body'])
            ?: ($this->extrairTag($retorno['body'], 'descResposta')
                ?: $this->extrairTag($retorno['body'], 'descRetorno')
                ?: ($sucessoEnvio ? 'Lote recebido — aguardando processamento' : $retorno['body']));

        return [
            'sucesso'                  => $sucessoEnvio && !$this->temOcorrenciaErro($retorno['body']),
            'assincrono'               => true,
            'aguardando_processamento' => $sucessoEnvio,
            'http_code'                => $retorno['http_code'],
            'xml_enviado'              => $loteXml,
            'xml_retorno'              => $retorno['body'],
            'protocolo'                => $this->extrairTag($retorno['body'], 'protocoloEnvio')
                                          ?: $this->extrairTag($retorno['body'], 'nrProtEnvio'),
            'codigo_retorno'           => $this->extrairTag($retorno['body'], 'cdResposta')
                                          ?: $this->extrairTag($retorno['body'], 'cdRetorno')
                                          ?: (string) $retorno['http_code'],
            'desc_retorno'             => $descRetorno,
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
        $retorno = $this->httpRequest('GET', $url);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        $sucesso = in_array($retorno['http_code'], [200, 201, 202], true);

        $recibos = [];
        $recibosPorId = [];
        $body = $retorno['body'] ?? '';

        if (preg_match_all('/<nrRecibo>([^<]+)<\/nrRecibo>/', $body, $m)) {
            $recibos = $m[1];
        }

        // Associa Id do evento ao recibo (retorno REINF)
        if (preg_match_all(
            '/(?:Id|id)=["\']?(ID[0-9A-Za-z]+)["\']?[\s\S]{0,800}?<nrRecibo>([^<]+)<\/nrRecibo>/',
            $body,
            $pares,
            PREG_SET_ORDER
        )) {
            foreach ($pares as $par) {
                $recibosPorId[$par[1]] = $par[2];
            }
        }

        // Alternativa: bloco retornoEvento com ideStatus e nrRecibo próximo ao id do evento
        if (empty($recibosPorId) && preg_match_all(
            '/<(?:evento|retEventos)[^>]*\s(?:Id|id)=["\']?(ID[0-9A-Za-z]+)["\']?[^>]*>[\s\S]*?<nrRecibo>([^<]+)<\/nrRecibo>/',
            $body,
            $pares2,
            PREG_SET_ORDER
        )) {
            foreach ($pares2 as $par) {
                $recibosPorId[$par[1]] = $par[2];
            }
        }

        return [
            'sucesso'         => $sucesso,
            'http_code'       => $retorno['http_code'],
            'xml_retorno'     => $body,
            'codigo_retorno'  => $this->extrairTag($body, 'cdResposta')
                                 ?: $this->extrairTag($body, 'cdRetorno')
                                 ?: (string) $retorno['http_code'],
            'desc_retorno'    => $this->extrairTag($body, 'descResposta')
                                 ?: $this->extrairTag($body, 'descRetorno')
                                 ?: $body,
            'recibos'         => $recibos,
            'recibos_por_id'  => $recibosPorId,
            'tempo_ms'        => $tempo,
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

    private function montarLote(string $nrInsc, array $eventosXml): string
    {
        $nrInsc = preg_replace('/\D/', '', $nrInsc) ?? '';
        $tpInsc = strlen($nrInsc) <= 11 ? '2' : '1';

        $eventosStr = '';
        foreach ($eventosXml as $i => $xml) {
            $xml = preg_replace('/<\?xml[^?]+\?>\s*/', '', $xml);
            $eventosStr .= "      <evento Id=\"evt_{$i}\">\n{$xml}\n      </evento>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<Reinf xmlns="http://www.reinf.esocial.gov.br/schemas/envioLoteEventosAssincrono/v1_00_00"' . "\n"
             . '       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n"
             . "  <envioLoteEventos>\n"
             . "    <ideContribuinte>\n"
             . "      <tpInsc>{$tpInsc}</tpInsc>\n"
             . "      <nrInsc>{$nrInsc}</nrInsc>\n"
             . "    </ideContribuinte>\n"
             . "    <eventos>\n"
             . $eventosStr
             . "    </eventos>\n"
             . "  </envioLoteEventos>\n"
             . "</Reinf>";
    }

    private function httpRequest(string $method, string $url, ?string $body = null): array
    {
        if ($url === '') {
            return [
                'http_code' => 0,
                'body'      => $method === 'POST' ? 'URL de envio não configurada' : 'URL de consulta não configurada',
            ];
        }

        $certFiles = $this->extrairCertificadoTemporario();
        if (!$certFiles) {
            return [
                'http_code' => 0,
                'body'      => $method === 'POST'
                    ? 'Erro: certificado ativo não encontrado ou senha inválida'
                    : 'Erro: certificado ativo não encontrado',
            ];
        }

        $userAgent = \App\Models\AppConfig::get()['reinf']['user_agent'] ?? 'EFD-REINF-WEB/1.0';

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $method === 'POST' ? 120 : 60,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => $method === 'POST'
                ? ['Content-Type: application/xml', 'Accept: application/xml']
                : ['Accept: application/xml'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT        => $certFiles['cert'],
            CURLOPT_SSLKEY         => $certFiles['key'],
            CURLOPT_SSLCERTTYPE    => 'PEM',
            CURLOPT_SSLKEYTYPE     => 'PEM',
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        @unlink($certFiles['cert']);
        @unlink($certFiles['key']);

        if ($response === false) {
            return ['http_code' => 0, 'body' => 'Erro cURL: ' . $error];
        }

        return ['http_code' => $httpCode, 'body' => $response];
    }

    /**
     * Extrai certificado ativo do banco, descriptografa senha e salva
     * PEM + KEY temporários (arquivos são apagados depois do request).
     */
    private function extrairCertificadoTemporario(): ?array
    {
        $repo      = new \App\Models\CertificadoRepository($this->db);
        $certAtivo = null;
        if ($this->userId) {
            if ($this->contribuinteId) {
                $certAtivo = $repo->findAtivoByContribuinte($this->contribuinteId, $this->userId);
            }
            if (!$certAtivo) {
                $certAtivo = $repo->findAtivoByUser($this->userId);
            }
        }
        if (!$certAtivo) {
            return null;
        }

        $caminho = (string) ($certAtivo['caminho'] ?? '');
        if ($caminho !== '' && !str_starts_with($caminho, '/')) {
            $caminho = rtrim(BASE_PATH, '/') . '/' . ltrim($caminho, './');
        }
        if ($caminho === '' || !file_exists($caminho)) {
            return null;
        }
        $certAtivo['caminho'] = $caminho;

        $senha = '';
        if (!empty($certAtivo['senha_encrypted'])) {
            $senha = CertificadoCrypto::decrypt(
                $certAtivo['senha_encrypted'],
                CertificadoCrypto::secretFromConfig()
            );
        }
        if ($senha === '') {
            return null;
        }

        $pfxContent = file_get_contents($certAtivo['caminho']);
        $certs      = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            return null;
        }

        $tmpDir   = sys_get_temp_dir();
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

    private function extrairOcorrencias(string $xml): string
    {
        if (!preg_match_all('/<ocorrencia>(.*?)<\/ocorrencia>/is', $xml, $blocos)) {
            return '';
        }
        $msgs = [];
        foreach ($blocos[1] as $bloco) {
            $cod  = '';
            $desc = '';
            if (preg_match('/<codigo>([^<]*)<\/codigo>/i', $bloco, $m)) {
                $cod = trim($m[1]);
            }
            if (preg_match('/<descricao>([^<]*)<\/descricao>/i', $bloco, $m)) {
                $desc = trim($m[1]);
            }
            if ($cod !== '' || $desc !== '') {
                $msgs[] = trim(($cod !== '' ? "{$cod}: " : '') . $desc);
            }
        }
        return implode(' | ', $msgs);
    }

    private function temOcorrenciaErro(string $xml): bool
    {
        // cdResposta 7 = lote não recebido; qualquer ocorrência tipo 1 também
        $cd = $this->extrairTag($xml, 'cdResposta') ?: $this->extrairTag($xml, 'cdRetorno');
        if (in_array($cd, ['7', '8', '9'], true)) {
            return true;
        }
        return (bool) preg_match('/<ocorrencia>/i', $xml);
    }
}
