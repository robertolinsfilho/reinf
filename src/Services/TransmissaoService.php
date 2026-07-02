<?php

namespace App\Services;

/**
 * Transmissão de lotes EFD-Reinf via API REST.
 * Referência: Manual do Desenvolvedor v2.7
 */
class TransmissaoService
{
    private int $tpAmb;
    private array $urlEnvio;
    private array $urlConsulta;
    private AssinaturaService $assinatura;

    public function __construct(private \PDO $db)
    {
        $config = \App\Models\AppConfig::get();
        $reinf  = $config['reinf'];

        $this->tpAmb       = $reinf['tp_amb'];
        $this->urlEnvio    = $reinf['ws_envio'];
        $this->urlConsulta = $reinf['ws_consulta'];
        $this->assinatura  = new AssinaturaService();
    }

    /**
     * Envia um lote de eventos assinados para a RFB.
     *
     * @param string $cnpj CNPJ do contribuinte
     * @param array  $xmls Lista de XMLs (já assinados ou não)
     * @param bool   $assinar Se true, assina cada XML antes do envio
     * @return array Resultado do envio
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

        // Limite oficial: 50 eventos por lote assíncrono
        if (count($eventosAssinados) > 50) {
            return ['sucesso' => false, 'erro' => 'Máximo de 50 eventos por lote (limite RFB).'];
        }

        $loteXml = $this->montarLote($cnpj, $eventosAssinados);
        $url     = $this->urlEnvio[$this->tpAmb] ?? '';
        $inicio  = microtime(true);
        $retorno = $this->httpPost($url, $loteXml);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        // No modelo assíncrono: HTTP 200 = protocolo recebido, ainda NÃO é aceitação final
        $sucessoEnvio = in_array($retorno['http_code'], [200, 202]);

        return [
            'sucesso'        => $sucessoEnvio,
            'assincrono'     => true,
            'aguardando_processamento' => $sucessoEnvio,
            'http_code'      => $retorno['http_code'],
            'xml_enviado'    => $loteXml,
            'xml_retorno'    => $retorno['body'],
            'protocolo'      => $this->extrairTag($retorno['body'], 'nrProtEnvio'),
            'codigo_retorno' => $this->extrairTag($retorno['body'], 'cdRetorno'),
            'desc_retorno'   => $this->extrairTag($retorno['body'], 'descRetorno')
                                ?: ($sucessoEnvio ? 'Lote recebido — aguardando processamento assíncrono' : ''),
            'tempo_ms'       => $tempo,
            'ambiente'       => $this->tpAmb,
        ];
    }

    /**
     * Consulta o resultado de um protocolo.
     */
    public function consultarProtocolo(string $cnpj, string $protocolo): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $url  = ($this->urlConsulta[$this->tpAmb] ?? '') . '/' . $protocolo;

        $inicio  = microtime(true);
        $retorno = $this->httpGet($url, $cnpj);
        $tempo   = (int) ((microtime(true) - $inicio) * 1000);

        $recibos = [];
        if (preg_match_all('/<nrRecibo>([^<]+)<\/nrRecibo>/', $retorno['body'], $m)) {
            $recibos = $m[1];
        }

        return [
            'sucesso'        => $retorno['http_code'] === 200,
            'http_code'      => $retorno['http_code'],
            'xml_retorno'    => $retorno['body'],
            'codigo_retorno' => $this->extrairTag($retorno['body'], 'cdRetorno'),
            'desc_retorno'   => $this->extrairTag($retorno['body'], 'descRetorno'),
            'recibos'        => $recibos,
            'tempo_ms'       => $tempo,
        ];
    }

    /**
     * Envio simulado (para quando não há certificado).
     */
    public function enviarSimulado(string $cnpj, array $xmls): array
    {
        $protocolo = 'SIM' . date('YmdHis') . str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);

        return [
            'sucesso'        => true,
            'simulado'       => true,
            'protocolo'      => $protocolo,
            'codigo_retorno' => '0',
            'desc_retorno'   => 'Lote recebido com sucesso (SIMULAÇÃO — certificado não configurado)',
            'xml_enviado'    => $this->montarLote(preg_replace('/\D/', '', $cnpj), $xmls),
            'xml_retorno'    => '<?xml version="1.0"?><retornoLoteEventos><cdRetorno>0</cdRetorno><descRetorno>Simulação OK</descRetorno><nrProtEnvio>' . $protocolo . '</nrProtEnvio></retornoLoteEventos>',
            'tempo_ms'       => 0,
            'ambiente'       => $this->tpAmb,
        ];
    }

    // ─── Internos ────────────────────────────────────────────

       private function montarLote(string $cnpj, array $eventosXml): string
    {
        $eventosStr = '';
        foreach ($eventosXml as $i => $xml) {
            // Remover declaração XML do evento individual (o lote já tem)
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

        $userAgent = \App\Models\AppConfig::get('reinf.user_agent') ?? 'EFD-REINF-WEB/1.0';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml',
                'Accept: application/xml',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT        => $this->findCert('pem'),
            CURLOPT_SSLKEY         => $this->findCert('key'),
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['http_code' => 0, 'body' => 'Erro cURL: ' . $error];
        }

        return ['http_code' => $httpCode, 'body' => $body];
    }

    private function httpGet(string $url, string $cnpj): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/xml',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $httpCode, 'body' => $body ?: ''];
    }

    private function findCert(string $ext): string
    {
        $config = \App\Models\AppConfig::get();
        $dir    = $config['reinf']['cert_path'] ?? '';
        $files  = glob($dir . '*.{' . $ext . '}', GLOB_BRACE);
        return $files[0] ?? '';
    }

    private function extrairTag(string $xml, string $tag): string
    {
        if (preg_match('/<' . $tag . '>([^<]*)<\/' . $tag . '>/', $xml, $m)) {
            return $m[1];
        }
        return '';
    }
}