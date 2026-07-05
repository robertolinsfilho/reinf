<?php

namespace App\Services;

/**
 * Geração de XML EFD-Reinf conforme leiaute v2.1.2
 * Namespace: http://www.reinf.esocial.gov.br/schemas/{evento}/v2_01_02
 */
class GeracaoXmlService
{
    private string $outputDir;
    private int $tpAmb;
    private int $indRetif = 1;
    private ?string $nrRecibo = null;
    private string $verProc;
    private int $procEmi;

    public function __construct(private \PDO $db)
    {
        $this->outputDir = BASE_PATH . '/public/uploads/xml/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $config = \App\Models\AppConfig::get();
        $this->tpAmb   = $config['reinf']['tp_amb'] ?? 2;
        $this->verProc = $config['reinf']['ver_proc'] ?? 'EFD-REINF-WEB-1.0';
        $this->procEmi = $config['reinf']['proc_emi'] ?? 1;
    }

    // ─── Entrada pública ─────────────────────────────────────

    public function gerar(array $competencia, array $eventos, int $indRetif = 1, ?string $nrRecibo = null): array
    {
        $arquivos = [];

        // Setar contexto de retificação para uso nos métodos privados
        $this->indRetif = $indRetif;
        $this->nrRecibo = $nrRecibo;

        foreach ($eventos as $evento) {
            $xml = match ($evento) {
                'R1000' => $this->gerarR1000($competencia),
                'R1070' => $this->gerarR1070($competencia),
                'R2010' => $this->gerarR2010($competencia),
                'R2020' => $this->gerarR2020($competencia),
                'R2060' => $this->gerarR2060($competencia),
                'R2099' => $this->gerarR2099($competencia),
                'R4010' => $this->gerarR4010($competencia),
                'R4020' => $this->gerarR4020($competencia),
                'R4099' => $this->gerarR4099($competencia),
                'R9000' => $this->gerarR9000($competencia),
                default => throw new \RuntimeException("Evento {$evento} não suportado."),
            };

            $sufixoRetif = $indRetif === 2 ? '_RETIF' : '';
            $nomeArq = sprintf(
                'REINF_%s%s_%s_%s_%s.xml',
                $evento, $sufixoRetif,
                $competencia['cnpj'],
                $competencia['periodo'],
                date('YmdHis')
            );
            $caminho = $this->outputDir . $nomeArq;
            file_put_contents($caminho, $xml);

            $arquivos[] = [
                'evento'  => $evento,
                'nome'    => $nomeArq,
                'caminho' => $caminho,
                'tamanho' => filesize($caminho),
                'hash'    => md5_file($caminho),
                'xml'     => $xml,
            ];
        }

        return $arquivos;
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function gerarId(string $cnpj): string
    {
        return 'ID' . $cnpj . date('YmdHis') . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function ideEvento(string $perApur, int $indRetif = 1, ?string $nrRecibo = null): string
    {
        $xml  = "<ideEvento>\n";
        $xml .= "            <indRetif>{$indRetif}</indRetif>\n";
        if ($indRetif === 2 && $nrRecibo) {
            $xml .= "            <nrRecibo>{$nrRecibo}</nrRecibo>\n";
        }
        $xml .= "            <perApur>{$perApur}</perApur>\n";
        $xml .= "            <tpAmb>{$this->tpAmb}</tpAmb>\n";
        $xml .= "            <procEmi>{$this->procEmi}</procEmi>\n";
        $xml .= "            <verProc>{$this->verProc}</verProc>\n";
        $xml .= "        </ideEvento>";
        return $xml;
    }

    private function ideContri(string $cnpj): string
    {
        $tp = strlen(preg_replace('/\D/', '', $cnpj)) <= 11 ? '2' : '1';
        return "<ideContri>\n"
             . "            <tpInsc>{$tp}</tpInsc>\n"
             . "            <nrInsc>" . preg_replace('/\D/', '', $cnpj) . "</nrInsc>\n"
             . "        </ideContri>";
    }

    private function envelope(string $eventoTag, string $namespace, string $id, string $conteudo): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<Reinf xmlns="http://www.reinf.esocial.gov.br/schemas/' . $namespace . '/v2_01_02">' . "\n"
             . '    <' . $eventoTag . ' id="' . $id . '">' . "\n"
             . $conteudo . "\n"
             . '    </' . $eventoTag . '>' . "\n"
             . '</Reinf>';
    }

    private function fmtVal(float|string|null $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, '.', '');
    }

    // ─── R-1000 · Informações do Contribuinte ────────────────

    private function gerarR1000(array $comp): string
    {
        $cnpj    = preg_replace('/\D/', '', $comp['cnpj']);
        $periodo = $comp['periodo'];
        $classif = $comp['classificacao_tributos'] ?? '01';
        $id      = $this->gerarId($cnpj);

        $body = "        {$this->ideEvento($periodo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoContri>\n"
              . "            <inclusao>\n"
              . "                <idePeriodo>\n"
              . "                    <iniValid>{$periodo}</iniValid>\n"
              . "                </idePeriodo>\n"
              . "                <infoCadastro>\n"
              . "                    <classTrib>{$classif}</classTrib>\n"
              . "                    <indEscrituracao>0</indEscrituracao>\n"
              . "                    <indDesoneracao>0</indDesoneracao>\n"
              . "                    <indAcordoIsenMulta>0</indAcordoIsenMulta>\n"
              . "                    <indSitPJ>0</indSitPJ>\n"
              . "                    <contato>\n"
              . "                        <nmCtt>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmCtt>\n"
              . "                        <cpfCtt>" . preg_replace('/\D/', '', $comp['cnpj'] ?? '') . "</cpfCtt>\n"
              . "                    </contato>\n"
              . "                </infoCadastro>\n"
              . "            </inclusao>\n"
              . "        </infoContri>";

        return $this->envelope('evtInfoContribuinte', 'evtInfoContribuinte', $id, $body);
    }

      private function gerarR1070(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r1070_processos WHERE contribuinte_id = ? AND status = 'ativo' ORDER BY data_inclusao");
        $stmt->execute([$comp['contribuinte_id']]);
        $processos = $stmt->fetchAll();

        if (empty($processos)) {
            throw new \RuntimeException("Nenhum processo cadastrado para o contribuinte.");
        }

        $processosXml = '';
        foreach ($processos as $p) {
            $processosXml .= "            <inclusao>\n"
                           . "                <idePeriodo>\n"
                           . "                    <iniValid>{$comp['periodo']}</iniValid>\n"
                           . "                </idePeriodo>\n"
                           . "                <ideProcesso>\n"
                           . "                    <tpProc>{$p['tipo_processo']}</tpProc>\n"
                           . "                    <nrProc>" . htmlspecialchars($p['numero_processo']) . "</nrProc>\n";

            if ($p['tipo_processo'] == 2) {
                $processosXml .= "                    <indAutoria>" . ($p['indicador_autoria'] ?? 1) . "</indAutoria>\n";
                if ($p['uf_vara']) {
                    $processosXml .= "                    <ufVara>{$p['uf_vara']}</ufVara>\n";
                }
                if ($p['cod_municipio']) {
                    $processosXml .= "                    <codMunic>{$p['cod_municipio']}</codMunic>\n";
                }
                if ($p['id_vara']) {
                    $processosXml .= "                    <idVara>{$p['id_vara']}</idVara>\n";
                }
            }

            $processosXml .= "                </ideProcesso>\n"
                           . "                <infoSusp>\n"
                           . "                    <indSusp>" . ($p['indicador_susp_exig'] ?? 0) . "</indSusp>\n";
            if ($p['data_decisao']) {
                $processosXml .= "                    <dtDecisao>{$p['data_decisao']}</dtDecisao>\n";
            }
            $processosXml .= "                    <indDeposito>" . ($p['indicador_deposito'] ?? 0) . "</indDeposito>\n"
                           . "                </infoSusp>\n"
                           . "            </inclusao>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoProcesso>\n"
              . $processosXml
              . "        </infoProcesso>";

        return $this->envelope('evtTabProcesso', 'evtTabProcesso', $id, $body);
    }

    // ─── R-2010 · Retenção INSS Serviços Tomados ─────────────

    private function gerarR2010(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2010 WHERE competencia_id = ? ORDER BY cnpj_prestador, data_emissao");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            throw new \RuntimeException("Nenhum registro R-2010 encontrado para esta competência.");
        }

        // Agrupar por prestador
        $porPrestador = [];
        foreach ($registros as $r) {
            $cnpjPrest = preg_replace('/\D/', '', $r['cnpj_prestador']);
            $porPrestador[$cnpjPrest][] = $r;
        }

        $prestadoresXml = '';
        foreach ($porPrestador as $cnpjPrest => $nfs) {
            $totalBruto  = array_sum(array_column($nfs, 'valor_bruto'));
            $totalRet    = array_sum(array_column($nfs, 'valor_retencao'));
            $tipoPrest   = $nfs[0]['tipo_insc_prestador'] ?? '1';

            $nfsXml = '';
            foreach ($nfs as $nf) {
                $nfsXml .= "                    <nfs>\n"
                         . "                        <serie>" . htmlspecialchars($nf['serie'] ?? '1') . "</serie>\n"
                         . "                        <numDocto>" . htmlspecialchars($nf['num_documento'] ?: '1') . "</numDocto>\n"
                         . "                        <dtEmissaoNF>" . ($nf['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF>\n"
                         . "                        <vlrBruto>" . $this->fmtVal($nf['valor_bruto']) . "</vlrBruto>\n"
                         . "                        <vlrBaseRet>" . $this->fmtVal($nf['valor_base_retencao'] ?? $nf['valor_bruto']) . "</vlrBaseRet>\n"
                         . "                        <vlrRetencao>" . $this->fmtVal($nf['valor_retencao']) . "</vlrRetencao>\n"
                         . "                        <vlrRetSub>0.00</vlrRetSub>\n"
                         . "                        <vlrNRetPrinc>0.00</vlrNRetPrinc>\n"
                         . "                        <vlrServicos15>" . $this->fmtVal($nf['valor_bruto']) . "</vlrServicos15>\n"
                         . "                        <vlrServicos20>0.00</vlrServicos20>\n"
                         . "                        <vlrServicos25>0.00</vlrServicos25>\n"
                         . "                        <vlrAdicional>0.00</vlrAdicional>\n"
                         . "                        <vlrNRetAdwordc>0.00</vlrNRetAdwordc>\n"
                         . "                    </nfs>\n";
            }

            $prestadoresXml .= "                <idePrestServ>\n"
                             . "                    <cnpjPrestador>{$cnpjPrest}</cnpjPrestador>\n"
                             . "                    <vlrTotalBruto>" . $this->fmtVal($totalBruto) . "</vlrTotalBruto>\n"
                             . "                    <vlrTotalBaseRet>" . $this->fmtVal($totalBruto) . "</vlrTotalBaseRet>\n"
                             . "                    <vlrTotalRetPrinc>" . $this->fmtVal($totalRet) . "</vlrTotalRetPrinc>\n"
                             . "                    <vlrTotalRetAdic>0.00</vlrTotalRetAdic>\n"
                             . "                    <vlrTotalNRetPrinc>0.00</vlrTotalNRetPrinc>\n"
                             . "                    <vlrTotalNRetAdic>0.00</vlrTotalNRetAdic>\n"
                             . $nfsXml
                             . "                </idePrestServ>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstabObra>\n"
              . "            <tpInscEstab>1</tpInscEstab>\n"
              . "            <nrInscEstab>{$cnpj}</nrInscEstab>\n"
              . "            <indObra>0</indObra>\n"
              . $prestadoresXml
              . "        </ideEstabObra>";

        return $this->envelope('evtServTom', 'evtServTom', $id, $body);
    }

    // ─── R-2020 · Retenção INSS Serviços Prestados ───────────

    private function gerarR2020(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2020 WHERE competencia_id = ? ORDER BY cnpj_tomador");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            throw new \RuntimeException("Nenhum registro R-2020 encontrado.");
        }

        $porTomador = [];
        foreach ($registros as $r) {
            $cnpjTom = preg_replace('/\D/', '', $r['cnpj_tomador']);
            $porTomador[$cnpjTom][] = $r;
        }

        $tomadoresXml = '';
        foreach ($porTomador as $cnpjTom => $nfs) {
            $totalBruto = array_sum(array_column($nfs, 'valor_bruto'));
            $totalRet   = array_sum(array_column($nfs, 'valor_retencao'));

            $nfsXml = '';
            foreach ($nfs as $nf) {
                $nfsXml .= "                    <nfs>\n"
                         . "                        <serie>" . htmlspecialchars($nf['serie'] ?? '1') . "</serie>\n"
                         . "                        <numDocto>" . htmlspecialchars($nf['num_documento'] ?: '1') . "</numDocto>\n"
                         . "                        <dtEmissaoNF>" . ($nf['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF>\n"
                         . "                        <vlrBruto>" . $this->fmtVal($nf['valor_bruto']) . "</vlrBruto>\n"
                         . "                        <vlrBaseRet>" . $this->fmtVal($nf['valor_base_retencao'] ?? $nf['valor_bruto']) . "</vlrBaseRet>\n"
                         . "                        <vlrRetencao>" . $this->fmtVal($nf['valor_retencao']) . "</vlrRetencao>\n"
                         . "                        <vlrRetSub>0.00</vlrRetSub>\n"
                         . "                        <vlrNRetPrinc>0.00</vlrNRetPrinc>\n"
                         . "                        <vlrServicos15>" . $this->fmtVal($nf['valor_bruto']) . "</vlrServicos15>\n"
                         . "                        <vlrServicos20>0.00</vlrServicos20>\n"
                         . "                        <vlrServicos25>0.00</vlrServicos25>\n"
                         . "                        <vlrAdicional>0.00</vlrAdicional>\n"
                         . "                        <vlrNRetAdwordc>0.00</vlrNRetAdwordc>\n"
                         . "                    </nfs>\n";
            }

            $tomadoresXml .= "                <ideTomador>\n"
                           . "                    <tpInscTomador>" . ($nfs[0]['tipo_insc_tomador'] ?? '1') . "</tpInscTomador>\n"
                           . "                    <nrInscTomador>{$cnpjTom}</nrInscTomador>\n"
                           . "                    <vlrTotalBruto>" . $this->fmtVal($totalBruto) . "</vlrTotalBruto>\n"
                           . "                    <vlrTotalBaseRet>" . $this->fmtVal($totalBruto) . "</vlrTotalBaseRet>\n"
                           . "                    <vlrTotalRetPrinc>" . $this->fmtVal($totalRet) . "</vlrTotalRetPrinc>\n"
                           . "                    <vlrTotalRetAdic>0.00</vlrTotalRetAdic>\n"
                           . "                    <vlrTotalNRetPrinc>0.00</vlrTotalNRetPrinc>\n"
                           . "                    <vlrTotalNRetAdic>0.00</vlrTotalNRetAdic>\n"
                           . $nfsXml
                           . "                </ideTomador>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstabPrest>\n"
              . "            <tpInscEstabPrest>1</tpInscEstabPrest>\n"
              . "            <nrInscEstabPrest>{$cnpj}</nrInscEstabPrest>\n"
              . $tomadoresXml
              . "        </ideEstabPrest>";

        return $this->envelope('evtServPrest', 'evtServPrest', $id, $body);
    }

    // ─── R-2060 · CPRB ───────────────────────────────────────

    private function gerarR2060(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2060 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            throw new \RuntimeException("Nenhum registro R-2060 encontrado.");
        }

        $atividadesXml = '';
        foreach ($registros as $r) {
            $bc = (float) ($r['valor_rec_bruta'] ?? 0) - (float) ($r['valor_exclusoes'] ?? 0);
            $vlrCprb = $bc * ((float) ($r['aliquota'] ?? 0) / 100);

            $atividadesXml .= "                <tipoCod>\n"
                            . "                    <codAtivEcon>" . htmlspecialchars($r['cnae']) . "</codAtivEcon>\n"
                            . "                    <vlrRecBrutaAtiv>" . $this->fmtVal($r['valor_rec_bruta']) . "</vlrRecBrutaAtiv>\n"
                            . "                    <vlrExcRecBruta>" . $this->fmtVal($r['valor_exclusoes']) . "</vlrExcRecBruta>\n"
                            . "                    <vlrAdicRecBruta>0.00</vlrAdicRecBruta>\n"
                            . "                    <vlrBcCPRB>" . $this->fmtVal($bc) . "</vlrBcCPRB>\n"
                            . "                    <vlrCPRBapur>" . $this->fmtVal($vlrCprb) . "</vlrCPRBapur>\n"
                            . "                </tipoCod>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab>\n"
              . "            <tpInscEstab>1</tpInscEstab>\n"
              . "            <nrInscEstab>{$cnpj}</nrInscEstab>\n"
              . $atividadesXml
              . "        </ideEstab>";

        return $this->envelope('evtInfoCPRB', 'evtCPRB', $id, $body);
    }

    // ─── R-2099 · Fechamento série R-2000 ────────────────────

    private function gerarR2099(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideRespInf>\n"
              . "            <nmResp>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmResp>\n"
              . "            <cpfResp>" . substr($cnpj, 0, 11) . "</cpfResp>\n"
              . "            <telefone></telefone>\n"
              . "            <email></email>\n"
              . "        </ideRespInf>\n"
              . "        <infoFech>\n"
              . "            <evtServTm>S</evtServTm>\n"
              . "            <evtServPr>S</evtServPr>\n"
              . "            <evtAssDespRec>N</evtAssDespRec>\n"
              . "            <evtAssDespRep>N</evtAssDespRep>\n"
              . "            <evtComProd>N</evtComProd>\n"
              . "            <evtCPRB>N</evtCPRB>\n"
              . "            <evtAquis>N</evtAquis>\n"
              . "        </infoFech>";

        return $this->envelope('evtFechaEvPer', 'evtFechamento', $id, $body);
    }

    // ─── R-4010 · Pagamentos PF (IRRF) ──────────────────────

    private function gerarR4010(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r4010 WHERE competencia_id = ? ORDER BY cpf_beneficiario, data_pagamento");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            throw new \RuntimeException("Nenhum registro R-4010 encontrado.");
        }

        // Agrupar por beneficiário
        $porBenef = [];
        foreach ($registros as $r) {
            $cpf = preg_replace('/\D/', '', $r['cpf_beneficiario']);
            $porBenef[$cpf][] = $r;
        }

        $benefXml = '';
        foreach ($porBenef as $cpf => $pagtos) {
            $pgtoXml = '';
            foreach ($pagtos as $p) {
                $pgtoXml .= "                    <infoPgto>\n"
                          . "                        <dtFG>" . $p['data_pagamento'] . "</dtFG>\n"
                          . "                        <vlrRendBruto>" . $this->fmtVal($p['valor_bruto']) . "</vlrRendBruto>\n"
                          . "                        <vlrRendTrib>" . $this->fmtVal($p['valor_base_ir'] ?? $p['valor_bruto']) . "</vlrRendTrib>\n"
                          . "                        <vlrIR>" . $this->fmtVal($p['valor_ir']) . "</vlrIR>\n";
                if ((float) ($p['valor_deducao'] ?? 0) > 0) {
                    $pgtoXml .= "                        <detDed>\n"
                              . "                            <indTpDeducao>1</indTpDeducao>\n"
                              . "                            <vlrDeducao>" . $this->fmtVal($p['valor_deducao']) . "</vlrDeducao>\n"
                              . "                        </detDed>\n";
                }
                $pgtoXml .= "                    </infoPgto>\n";
            }

            $benefXml .= "                <ideBenef>\n"
                       . "                    <cpfBenef>{$cpf}</cpfBenef>\n"
                       . "                    <nmBenef>" . htmlspecialchars($pagtos[0]['nome_beneficiario'] ?? '') . "</nmBenef>\n"
                       . "                    <ideEvtAdic>\n"
                       . "                        <tpIsencao>0</tpIsencao>\n"
                       . "                        <natRend>" . ($pagtos[0]['natureza_rendimento'] ?? '10001') . "</natRend>\n"
                       . "                    </ideEvtAdic>\n"
                       . $pgtoXml
                       . "                </ideBenef>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab>\n"
              . "            <tpInscEstab>1</tpInscEstab>\n"
              . "            <nrInscEstab>{$cnpj}</nrInscEstab>\n"
              . $benefXml
              . "        </ideEstab>";

        return $this->envelope('evtRetPF', 'evtRetPF', $id, $body);
    }

    // ─── R-4020 · Pagamentos PJ (IRRF/CSLL/COFINS/PIS) ──────

    private function gerarR4020(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("
            SELECT * FROM r4020
            WHERE competencia_id = ?
            ORDER BY cnpj_beneficiario, natureza_rendimento, data_pagamento
        ");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            throw new \RuntimeException("Nenhum registro R-4020 encontrado.");
        }

        // Agrupa por CNPJ beneficiário + natureza (cada combinação vira um <ideBenef>)
        $grupos = [];
        foreach ($registros as $r) {
            $cnpjBenef = preg_replace('/\D/', '', $r['cnpj_beneficiario']);
            $natRend   = $r['cod_tipo_servico'] ?? $r['natureza_rendimento'] ?? '00000';
            $chave     = "{$cnpjBenef}|{$natRend}";
            $grupos[$chave][] = $r;
        }

        $benefXml = '';
        foreach ($grupos as $chave => $pagtos) {
            [$cnpjBenef, $natRend] = explode('|', $chave);
            $p0 = $pagtos[0];

            $pgtoXml = '';
            foreach ($pagtos as $p) {
                $vBruto  = (float) ($p['valor_bruto'] ?? 0);
                $vBaseIR = (float) ($p['valor_base_ir'] ?? $vBruto);
                $vIR     = (float) ($p['valor_ir'] ?? 0);
                $vCSLL   = (float) ($p['valor_csll'] ?? 0);
                $vCofins = (float) ($p['valor_cofins'] ?? 0);
                $vPIS    = (float) ($p['valor_pis'] ?? 0);
                $vCSRF   = (float) ($p['vl_csrf_agregado'] ?? 0);
                $numDoc  = htmlspecialchars((string)($p['num_nfs'] ?? ''));
                $obs     = htmlspecialchars((string)($p['observacoes'] ?? ''));

                $pgtoXml .= "                    <infoPgto>\n"
                          . "                        <dtFG>" . $p['data_pagamento'] . "</dtFG>\n";
                if ($numDoc) {
                    $pgtoXml .= "                        <numDocto>{$numDoc}</numDocto>\n";
                }
                $pgtoXml .= "                        <vlrBruto>" . $this->fmtVal($vBruto) . "</vlrBruto>\n"
                          . "                        <vlrBaseIR>" . $this->fmtVal($vBaseIR) . "</vlrBaseIR>\n"
                          . "                        <vlrIR>" . $this->fmtVal($vIR) . "</vlrIR>\n";

                // CSRF (Contribuições Sociais Retidas na Fonte)
                if ($vCSRF > 0) {
                    $pgtoXml .= "                        <vlrAgregCSRF>" . $this->fmtVal($vCSRF) . "</vlrAgregCSRF>\n";
                }
                if ($vCSLL > 0) {
                    $pgtoXml .= "                        <vlrBaseCSLL>" . $this->fmtVal($vBruto) . "</vlrBaseCSLL>\n"
                              . "                        <vlrCSLL>" . $this->fmtVal($vCSLL) . "</vlrCSLL>\n";
                }
                if ($vCofins > 0) {
                    $pgtoXml .= "                        <vlrBaseCofins>" . $this->fmtVal($vBruto) . "</vlrBaseCofins>\n"
                              . "                        <vlrCofins>" . $this->fmtVal($vCofins) . "</vlrCofins>\n";
                }
                if ($vPIS > 0) {
                    $pgtoXml .= "                        <vlrBasePP>" . $this->fmtVal($vBruto) . "</vlrBasePP>\n"
                              . "                        <vlrPP>" . $this->fmtVal($vPIS) . "</vlrPP>\n";
                }

                // Se judicial, adicionar bloco de processo
                if (!empty($p['indicador_judicial']) && !empty($p['numero_processo'])) {
                    $pgtoXml .= "                        <infoProcRet>\n"
                              . "                            <tpProcRet>1</tpProcRet>\n"
                              . "                            <nrProcRet>" . htmlspecialchars($p['numero_processo']) . "</nrProcRet>\n"
                              . "                            <codSusp></codSusp>\n"
                              . "                        </infoProcRet>\n";
                }

                if ($obs) {
                    $pgtoXml .= "                        <observ>{$obs}</observ>\n";
                }

                $pgtoXml .= "                    </infoPgto>\n";
            }

            $benefXml .= "                <ideBenef>\n"
                       . "                    <cnpjBenef>{$cnpjBenef}</cnpjBenef>\n"
                       . "                    <nmBenef>" . htmlspecialchars($p0['razao_social_beneficiario'] ?? '') . "</nmBenef>\n"
                       . "                    <isenImun>0</isenImun>\n"
                       . "                    <ideEvtAdic>\n"
                       . "                        <natRend>{$natRend}</natRend>\n"
                       . "                    </ideEvtAdic>\n"
                       . $pgtoXml
                       . "                </ideBenef>\n";
        }

        // Determina CNPJ do estabelecimento (contribuinte pagador) — pode ser diferente do contribuinte matriz
        $cnpjEstab = $registros[0]['cnpj_contribuinte'] ?? $cnpj;
        $cnpjEstab = preg_replace('/\D/', '', $cnpjEstab) ?: $cnpj;

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab>\n"
              . "            <tpInscEstab>1</tpInscEstab>\n"
              . "            <nrInscEstab>{$cnpjEstab}</nrInscEstab>\n"
              . $benefXml
              . "        </ideEstab>";

        return $this->envelope('evtRetPJ', 'evtRetPJ', $id, $body);
    }

    // ─── R-4099 · Fechamento série R-4000 ────────────────────

    private function gerarR4099(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideRespInf>\n"
              . "            <nmResp>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmResp>\n"
              . "            <cpfResp>" . substr($cnpj, 0, 11) . "</cpfResp>\n"
              . "            <telefone></telefone>\n"
              . "            <email></email>\n"
              . "        </ideRespInf>\n"
              . "        <infoFech>\n"
              . "            <fechRet>S</fechRet>\n"
              . "        </infoFech>";

        return $this->envelope('evtFechamento', 'evtFech', $id, $body);
    }

    // ─── R-9000 · Exclusão de evento ─────────────────────────

    private function gerarR9000(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $nrRecibo = $comp['num_recibo'] ?? '';
        $tpEvt    = $comp['tipo_evento_exclusao'] ?? 'R-2010';

        if (empty($nrRecibo)) {
            throw new \RuntimeException("R-9000 exige número do recibo do evento a excluir. Informe o recibo.");
        }

        $body = "        <ideEvento>\n"
              . "            <tpAmb>{$this->tpAmb}</tpAmb>\n"
              . "            <procEmi>{$this->procEmi}</procEmi>\n"
              . "            <verProc>{$this->verProc}</verProc>\n"
              . "        </ideEvento>\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoExclusao>\n"
              . "            <tpEvento>{$tpEvt}</tpEvento>\n"
              . "            <nrRecEvt>{$nrRecibo}</nrRecEvt>\n"
              . "            <perApur>{$comp['periodo']}</perApur>\n"
              . "        </infoExclusao>";

        return $this->envelope('evtExclusao', 'evtExclusao', $id, $body);
    }
}