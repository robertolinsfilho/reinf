<?php

namespace App\Services;

/**
 * Valida XML EFD-Reinf contra schema XSD oficial.
 */
class ValidacaoXmlService
{
    private string $xsdDir;

    /**
     * Mapa evento -> nome do XSD
     */
    private const MAPA_XSD = [
        'R1000' => 'evtInfoContribuinte',
        'R1070' => 'evtTabProcesso',
        'R2010' => 'evtServTom',
        'R2020' => 'evtServPrest',
        'R2055' => 'evtAqProd',
        'R2060' => 'evtCPRB',
        'R2099' => 'evtFechamento',
        'R4010' => 'evtRetPF',
        'R4020' => 'evtRetPJ',
        'R4099' => 'evtFechRet',
        'R9000' => 'evtExclusao',
    ];

    private const VERSAO = 'v2_01_02';

    public function __construct()
    {
        $this->xsdDir = BASE_PATH . '/storage/xsd/';
    }

    /**
     * Valida o XML contra o XSD do evento.
     *
     * @return array{valido: bool, erros: array<string>, aviso?: string}
     */
    public function validar(string $xml, string $evento): array
    {
        $nomeXsd = self::MAPA_XSD[$evento] ?? null;

        if (!$nomeXsd) {
            return [
                'valido' => false,
                'erros'  => ["Evento {$evento} não suportado para validação."],
            ];
        }

        $caminhoXsd = $this->xsdDir . $nomeXsd . '-' . self::VERSAO . '.xsd';

        if (!file_exists($caminhoXsd)) {
            return [
                'valido' => true,
                'erros'  => [],
                'aviso'  => "XSD não disponível para {$evento}. Validação ignorada. " .
                            "Coloque o arquivo {$nomeXsd}-" . self::VERSAO . ".xsd em storage/xsd/.",
            ];
        }

        // Habilitar captura de erros internos
        $usarErrosInternos = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml)) {
            $erros = $this->coletarErros();
            libxml_use_internal_errors($usarErrosInternos);
            return ['valido' => false, 'erros' => $erros];
        }

        $valido = @$dom->schemaValidate($caminhoXsd);
        $erros  = $valido ? [] : $this->coletarErros();

        libxml_clear_errors();
        libxml_use_internal_errors($usarErrosInternos);

        return ['valido' => $valido, 'erros' => $erros];
    }

    /**
     * Valida um conjunto de XMLs (vários eventos).
     *
     * @param array<array{evento: string, xml: string, nome?: string}> $arquivos
     */
    public function validarLote(array $arquivos): array
    {
        $resultados = [];
        $todosValidos = true;
        $temAviso     = false;

        foreach ($arquivos as $arq) {
            $res = $this->validar($arq['xml'], $arq['evento']);
            $resultados[] = [
                'evento' => $arq['evento'],
                'nome'   => $arq['nome'] ?? $arq['evento'],
                'valido' => $res['valido'],
                'erros'  => $res['erros'],
                'aviso'  => $res['aviso'] ?? null,
            ];
            if (!$res['valido']) $todosValidos = false;
            if (!empty($res['aviso'])) $temAviso = true;
        }

        return [
            'todos_validos' => $todosValidos,
            'tem_aviso'     => $temAviso,
            'resultados'    => $resultados,
        ];
    }

    /**
     * Lista quais XSDs estão presentes em storage/xsd/.
     */
    public function statusXsds(): array
    {
        $status = [];
        foreach (self::MAPA_XSD as $evento => $nome) {
            $arquivo = $this->xsdDir . $nome . '-' . self::VERSAO . '.xsd';
            $status[$evento] = [
                'nome_arquivo' => $nome . '-' . self::VERSAO . '.xsd',
                'instalado'    => file_exists($arquivo),
                'tamanho'      => file_exists($arquivo) ? filesize($arquivo) : 0,
            ];
        }
        return $status;
    }

    private function coletarErros(): array
    {
        $erros = [];
        foreach (libxml_get_errors() as $err) {
            $tipo = match ($err->level) {
                LIBXML_ERR_WARNING => 'AVISO',
                LIBXML_ERR_ERROR   => 'ERRO',
                LIBXML_ERR_FATAL   => 'FATAL',
                default            => 'INFO',
            };
            $erros[] = "[{$tipo}] Linha {$err->line}: " . trim($err->message);
        }
        return $erros;
    }
}