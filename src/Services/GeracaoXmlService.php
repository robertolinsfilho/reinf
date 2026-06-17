<?php

namespace App\Services;

class GeracaoXmlService
{
    private string $outputDir;

    public function __construct(private \PDO $db)
    {
        $this->outputDir = BASE_PATH . '/public/uploads/xml/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function gerar(array $competencia, array $eventos): array
    {
        $arquivos = [];

        foreach ($eventos as $evento) {
            $xml = match ($evento) {
                'R1000' => $this->gerarR1000($competencia),
                'R2010' => $this->gerarR2010($competencia),
                'R2020' => $this->gerarR2020($competencia),
                'R2050' => $this->gerarR2050($competencia),
                'R2055' => $this->gerarR2055($competencia),
                'R2060' => $this->gerarR2060($competencia),
                'R9000' => $this->gerarR9000($competencia),
                default => throw new \RuntimeException("Evento {$evento} não suportado."),
            };

            $nomeArq = "REINF_{$evento}_{$competencia['cnpj']}_{$competencia['periodo']}_" . date('YmdHis') . ".xml";
            $caminho = $this->outputDir . $nomeArq;

            file_put_contents($caminho, $xml);

            $arquivos[] = [
                'nome'    => $nomeArq,
                'caminho' => $caminho,
                'tamanho' => filesize($caminho),
                'hash'    => md5_file($caminho),
            ];
        }

        return $arquivos;
    }

    private function gerarR1000(array $comp): string
    {
        $cnpj    = $comp['cnpj'];
        $periodo = $comp['periodo'];
        $classif = $comp['classificacao_tributos'] ?? '01';
        $dtIni   = $periodo . '-01';
        $dtFim   = date('Y-m-t', strtotime($dtIni));

        return $this->envelope("R-1000", $cnpj, $periodo, "
        <evtInfoContribuinte id=\"ID_{$cnpj}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$periodo}</perApur>
                <tpAmb>2</tpAmb>
                <aplicEmi>1</aplicEmi>
                <verAplic>EFD-REINF-PHP-1.0</verAplic>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$cnpj}</nrInsc>
            </ideContri>
            <infoContri>
                <inclusao>
                    <idePeriodo>
                        <iniValid>" . substr($periodo, 0, 7) . "</iniValid>
                    </idePeriodo>
                    <dadosContri>
                        <classTrib>{$classif}</classTrib>
                        <indEscrituracao>1</indEscrituracao>
                        <indDesoneracao>N</indDesoneracao>
                        <indAcordoIsenMulta>N</indAcordoIsenMulta>
                        <indSitPJ>0</indSitPJ>
                    </dadosContri>
                </inclusao>
            </infoContri>
        </evtInfoContribuinte>");
    }

    private function gerarR2010(array $comp): string
    {
        $stmt = $this->db->prepare("SELECT * FROM r2010 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2010 encontrado.");

        $totalBruto  = array_sum(array_column($registros, 'valor_bruto'));
        $totalRetenc = array_sum(array_column($registros, 'valor_retencao'));

        $itens = '';
        foreach ($registros as $r) {
            $itens .= "
                <nfSe>
                    <indTpNFS>2</indTpNFS>
                    <serie>" . htmlspecialchars($r['num_documento'] ?: '001') . "</serie>
                    <nrDoc>" . htmlspecialchars($r['num_documento'] ?: '1') . "</nrDoc>
                    <dtEmissaoNF>{$r['data_emissao']}</dtEmissaoNF>
                    <vlrBruto>" . number_format($r['valor_bruto'], 2, '.', '') . "</vlrBruto>
                    <vlrRetencao>" . number_format($r['valor_retencao'], 2, '.', '') . "</vlrRetencao>
                    <vlrRetencaoAjust>" . number_format($r['valor_retencao_ajustada'] ?? $r['valor_retencao'], 2, '.', '') . "</vlrRetencaoAjust>
                    <vlrDescSenar>" . number_format($r['valor_desc_senar'] ?? 0, 2, '.', '') . "</vlrDescSenar>
                </nfSe>";
        }

        return $this->envelope("R-2010", $comp['cnpj'], $comp['periodo'], "
        <evtServTom id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$comp['periodo']}</perApur>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            <ideEstab>
                <tpInscEstab>1</tpInscEstab>
                <nrInscEstab>{$comp['cnpj']}</nrInscEstab>
                " . $this->gerarItensPrestadores($registros) . "
            </ideEstab>
        </evtServTom>");
    }

    private function gerarItensPrestadores(array $registros): string
    {
        // Agrupar por prestador
        $porPrestador = [];
        foreach ($registros as $r) {
            $cnpj = $r['cnpj_prestador'];
            $porPrestador[$cnpj][] = $r;
        }

        $xml = '';
        foreach ($porPrestador as $cnpj => $itens) {
            $totalBruto  = array_sum(array_column($itens, 'valor_bruto'));
            $totalRetenc = array_sum(array_column($itens, 'valor_retencao'));
            $tipo        = $itens[0]['tipo_insc_prestador'] ?? '1';
            $razao       = htmlspecialchars($itens[0]['razao_social_prestador'] ?? '');

            $nfs = '';
            foreach ($itens as $r) {
                $nfs .= "
                    <nfSe>
                        <serie>001</serie>
                        <nrDoc>" . htmlspecialchars($r['num_documento'] ?: '1') . "</nrDoc>
                        <dtEmissaoNF>" . ($r['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF>
                        <vlrBruto>" . number_format($r['valor_bruto'], 2, '.', '') . "</vlrBruto>
                        <vlrRetencao>" . number_format($r['valor_retencao'], 2, '.', '') . "</vlrRetencao>
                        <vlrRetencaoAjust>" . number_format($r['valor_retencao_ajustada'] ?? $r['valor_retencao'], 2, '.', '') . "</vlrRetencaoAjust>
                        <vlrDescSenar>" . number_format($r['valor_desc_senar'] ?? 0, 2, '.', '') . "</vlrDescSenar>
                    </nfSe>";
            }

            $xml .= "
                <idePrestador>
                    <tpInscPrestador>{$tipo}</tpInscPrestador>
                    <nrInscPrestador>{$cnpj}</nrInscPrestador>
                    <vlrTotalBruto>" . number_format($totalBruto, 2, '.', '') . "</vlrTotalBruto>
                    <vlrTotalRetencaoOrigem>" . number_format($totalRetenc, 2, '.', '') . "</vlrTotalRetencaoOrigem>
                    {$nfs}
                </idePrestador>";
        }
        return $xml;
    }

    private function gerarR2020(array $comp): string
    {
        $stmt = $this->db->prepare("SELECT * FROM r2020 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2020 encontrado.");

        $tomadores = '';
        foreach ($registros as $r) {
            $tomadores .= "
                <ideTomador>
                    <tpInscTomador>{$r['tipo_insc_tomador']}</tpInscTomador>
                    <nrInscTomador>{$r['cnpj_tomador']}</nrInscTomador>
                    <nfSePrestada>
                        <nrDoc>" . htmlspecialchars($r['num_documento'] ?: '1') . "</nrDoc>
                        <dtEmissaoNF>" . ($r['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF>
                        <vlrBruto>" . number_format($r['valor_bruto'], 2, '.', '') . "</vlrBruto>
                        <vlrRetencao>" . number_format($r['valor_retencao'], 2, '.', '') . "</vlrRetencao>
                        <vlrRetencaoAjust>" . number_format($r['valor_retencao_ajustada'] ?? $r['valor_retencao'], 2, '.', '') . "</vlrRetencaoAjust>
                    </nfSePrestada>
                </ideTomador>";
        }

        return $this->envelope("R-2020", $comp['cnpj'], $comp['periodo'], "
        <evtServPrest id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$comp['periodo']}</perApur>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            <ideEstabPrest>
                <tpInscEstabPrest>1</tpInscEstabPrest>
                <nrInscEstabPrest>{$comp['cnpj']}</nrInscEstabPrest>
                {$tomadores}
            </ideEstabPrest>
        </evtServPrest>");
    }

    private function gerarR2050(array $comp): string
    {
        $stmt = $this->db->prepare("SELECT * FROM r2050 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2050 encontrado.");

        $itens = '';
        foreach ($registros as $r) {
            $itens .= "
                <ideAdquirente>
                    <tpInscAdquirente>1</tpInscAdquirente>
                    <nrInscAdquirente>{$r['cnpj_adquirente']}</nrInscAdquirente>
                    <vlrComercializado>" . number_format($r['valor_comercializacao'], 2, '.', '') . "</vlrComercializado>
                    <vlrCPDescPR>" . number_format($r['valor_contribuicao_previdenciaria'], 2, '.', '') . "</vlrCPDescPR>
                    <vlrSenar>" . number_format($r['valor_senar'], 2, '.', '') . "</vlrSenar>
                    <dtOper>" . ($r['data_operacao'] ?: date('Y-m-d')) . "</dtOper>
                </ideAdquirente>";
        }

        return $this->envelope("R-2050", $comp['cnpj'], $comp['periodo'], "
        <evtComProd id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$comp['periodo']}</perApur>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            {$itens}
        </evtComProd>");
    }

    private function gerarR2055(array $comp): string
    {
        $stmt = $this->db->prepare("SELECT * FROM r2055 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2055 encontrado.");

        $itens = '';
        foreach ($registros as $r) {
            $itens .= "
                <ideProdRural>
                    <tpInscProdRural>2</tpInscProdRural>
                    <nrInscProdRural>{$r['cpf_produtor']}</nrInscProdRural>
                    <vlrAquisicao>" . number_format($r['valor_aquisicao'], 2, '.', '') . "</vlrAquisicao>
                    <vlrDescCP>" . number_format($r['valor_retencao'], 2, '.', '') . "</vlrDescCP>
                    <vlrDescSenar>" . number_format($r['valor_senar'], 2, '.', '') . "</vlrDescSenar>
                    <dtAquisicao>" . ($r['data_aquisicao'] ?: date('Y-m-d')) . "</dtAquisicao>
                </ideProdRural>";
        }

        return $this->envelope("R-2055", $comp['cnpj'], $comp['periodo'], "
        <evtAqProd id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$comp['periodo']}</perApur>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            <ideEstab>
                <tpInscEstab>1</tpInscEstab>
                <nrInscEstab>{$comp['cnpj']}</nrInscEstab>
                {$itens}
            </ideEstab>
        </evtAqProd>");
    }

    private function gerarR2060(array $comp): string
    {
        $stmt = $this->db->prepare("SELECT * FROM r2060 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2060 encontrado.");

        $itens = '';
        foreach ($registros as $r) {
            $itens .= "
                <ideEstabObra>
                    <tpInscEstabObra>1</tpInscEstabObra>
                    <nrInscEstabObra>{$comp['cnpj']}</nrInscEstabObra>
                    <indConstrCivil>{$r['ind_constr_civil']}</indConstrCivil>
                    <cnae>{$r['cnae']}</cnae>
                    <vlrRecBruta>" . number_format($r['valor_rec_bruta'], 2, '.', '') . "</vlrRecBruta>
                    <vlrRecBrutaExcl>" . number_format($r['valor_rec_bruta_excl'], 2, '.', '') . "</vlrRecBrutaExcl>
                    <vlrBaseCalculo>" . number_format($r['valor_base_calculo'], 2, '.', '') . "</vlrBaseCalculo>
                    <aliqAplic>" . number_format($r['aliquota'], 2, '.', '') . "</aliqAplic>
                    <vlrContribuicaoPrevidenciaria>" . number_format($r['valor_contribuicao'], 2, '.', '') . "</vlrContribuicaoPrevidenciaria>
                </ideEstabObra>";
        }

        return $this->envelope("R-2060", $comp['cnpj'], $comp['periodo'], "
        <evtCPRB id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <perApur>{$comp['periodo']}</perApur>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            {$itens}
        </evtCPRB>");
    }

    private function gerarR9000(array $comp): string
    {
        return $this->envelope("R-9000", $comp['cnpj'], $comp['periodo'], "
        <evtExclusao id=\"ID_{$comp['cnpj']}_" . date('YmdHis') . "\">
            <ideEvento>
                <indRetif>1</indRetif>
                <tpAmb>2</tpAmb>
                <procEmi>1</procEmi>
                <verProc>EFD-REINF-PHP-1.0</verProc>
            </ideEvento>
            <ideContri>
                <tpInsc>1</tpInsc>
                <nrInsc>{$comp['cnpj']}</nrInsc>
            </ideContri>
            <ideEvtExcl>
                <tpEvt>R-2010</tpEvt>
                <nrRecEvtExcl>INFORME_NUMERO_RECIBO</nrRecEvtExcl>
            </ideEvtExcl>
        </evtExclusao>");
    }

    private function envelope(string $evento, string $cnpj, string $periodo, string $conteudo): string
    {
        $eventoSemHifen = str_replace('-', '', $evento);
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            "<Reinf xmlns=\"http://www.esocial.gov.br/schema/reinf/{$eventoSemHifen}/v2_01_00\">\n" .
            "    <eve{$eventoSemHifen}>\n" .
            $conteudo .
            "\n    </eve{$eventoSemHifen}>\n" .
            "</Reinf>";
    }
}
