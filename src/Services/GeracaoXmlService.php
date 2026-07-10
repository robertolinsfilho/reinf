<?php

namespace App\Services;

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

    public function gerar(array $competencia, array $eventos, int $indRetif = 1, ?string $nrRecibo = null): array
    {
        $arquivos = [];
        $this->indRetif = $indRetif;
        $this->nrRecibo = $nrRecibo;

        foreach ($eventos as $evento) {
            // R-4020 pode gerar múltiplos XMLs (um por beneficiário)
            if ($evento === 'R4020') {
                $xmls = $this->gerarR4020PorBeneficiario($competencia);
                foreach ($xmls as $i => $xml) {
                    $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif, $i);
                }
                continue;
            }

            $xml = match ($evento) {
                'R1000' => $this->gerarR1000($competencia),
                'R1070' => $this->gerarR1070($competencia),
                'R2010' => $this->gerarR2010($competencia),
                'R2020' => $this->gerarR2020($competencia),
                'R2060' => $this->gerarR2060($competencia),
                'R2099' => $this->gerarR2099($competencia),
                'R4010' => $this->gerarR4010($competencia),
                'R4099' => $this->gerarR4099($competencia),
                'R9000' => $this->gerarR9000($competencia),
                default => throw new \RuntimeException("Evento {$evento} não suportado."),
            };

            $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif);
        }

        return $arquivos;
    }

    private function salvarXml(string $evento, array $competencia, string $xml, int $indRetif, int $seq = 0): array
    {
        $sufixoRetif = $indRetif === 2 ? '_RETIF' : '';
        $sufixoSeq   = $seq > 0 ? "_{$seq}" : '';
        $nomeArq = sprintf('REINF_%s%s_%s_%s_%s%s.xml', $evento, $sufixoRetif, $competencia['cnpj'], $competencia['periodo'], date('YmdHis'), $sufixoSeq);
        $caminho = $this->outputDir . $nomeArq;
        file_put_contents($caminho, $xml);

        return [
            'evento'  => $evento,
            'nome'    => $nomeArq,
            'caminho' => $caminho,
            'tamanho' => filesize($caminho),
            'hash'    => md5_file($caminho),
            'xml'     => $xml,
        ];
    }

    private function gerarId(string $cnpj): string
    {
        // ID = 'ID' + [1-2] + 14 dígitos CNPJ + timestamp YYYYMMDDhhmmss + sequencial 5 dígitos
        // Total: 2 + 1 + 14 + 14 + 5 = 36 chars (padrão XSD)
        return 'ID1' . str_pad($cnpj, 14, '0', STR_PAD_LEFT) . date('YmdHis') . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
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
        return "<ideContri>\n            <tpInsc>{$tp}</tpInsc>\n            <nrInsc>" . preg_replace('/\D/', '', $cnpj) . "</nrInsc>\n        </ideContri>";
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
        // XSD EFD-Reinf: separador decimal é ponto (ex: 1234.56)
        return number_format((float) ($valor ?? 0), 2, '.', '');
    }

    // ═══ R-1000 ═══════════════════════════════════════

    private function gerarR1000(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);
        $classif = $comp['classificacao_tributos'] ?? '01';

        $body = "        {$this->ideEvento($comp['periodo'])}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoContri>\n"
              . "            <inclusao>\n"
              . "                <idePeriodo><iniValid>{$comp['periodo']}</iniValid></idePeriodo>\n"
              . "                <infoCadastro>\n"
              . "                    <classTrib>{$classif}</classTrib>\n"
              . "                    <indEscrituracao>0</indEscrituracao>\n"
              . "                    <indDesoneracao>0</indDesoneracao>\n"
              . "                    <indAcordoIsenMulta>0</indAcordoIsenMulta>\n"
              . "                    <indSitPJ>0</indSitPJ>\n"
              . "                    <contato>\n"
              . "                        <nmCtt>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmCtt>\n"
              . "                        <cpfCtt>" . substr($cnpj, 0, 11) . "</cpfCtt>\n"
              . "                    </contato>\n"
              . "                </infoCadastro>\n"
              . "            </inclusao>\n"
              . "        </infoContri>";

        return $this->envelope('evtInfoContribuinte', 'evtInfoContribuinte', $id, $body);
    }

    // ═══ R-1070 ═══════════════════════════════════════

    private function gerarR1070(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r1070_processos WHERE contribuinte_id = ? AND status = 'ativo'");
        $stmt->execute([$comp['contribuinte_id']]);
        $processos = $stmt->fetchAll();

        if (empty($processos)) {
            throw new \RuntimeException("Nenhum processo cadastrado.");
        }

        $processosXml = '';
        foreach ($processos as $p) {
            $processosXml .= "            <inclusao>\n"
                          . "                <idePeriodo><iniValid>{$comp['periodo']}</iniValid></idePeriodo>\n"
                          . "                <ideProcesso>\n"
                          . "                    <tpProc>{$p['tipo_processo']}</tpProc>\n"
                          . "                    <nrProc>" . htmlspecialchars($p['numero_processo']) . "</nrProc>\n"
                          . "                </ideProcesso>\n"
                          . "                <infoSusp><indSusp>" . ($p['indicador_susp_exig'] ?? 0) . "</indSusp><indDeposito>" . ($p['indicador_deposito'] ?? 0) . "</indDeposito></infoSusp>\n"
                          . "            </inclusao>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoProcesso>\n" . $processosXml . "        </infoProcesso>";

        return $this->envelope('evtTabProcesso', 'evtTabProcesso', $id, $body);
    }

    // ═══ R-2010 ═══════════════════════════════════════

    private function gerarR2010(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2010 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2010.");

        $porPrest = [];
        foreach ($registros as $r) {
            $porPrest[preg_replace('/\D/', '', $r['cnpj_prestador'])][] = $r;
        }

        $xml = '';
        foreach ($porPrest as $cnpjP => $nfs) {
            $totBruto = array_sum(array_column($nfs, 'valor_bruto'));
            $totRet   = array_sum(array_column($nfs, 'valor_retencao'));
            $nfsXml = '';
            foreach ($nfs as $n) {
                $nfsXml .= "                    <nfs><serie>" . ($n['serie'] ?? '1') . "</serie><numDocto>" . ($n['num_documento'] ?: '1') . "</numDocto><dtEmissaoNF>" . ($n['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF><vlrBruto>" . $this->fmtVal($n['valor_bruto']) . "</vlrBruto><vlrBaseRet>" . $this->fmtVal($n['valor_bruto']) . "</vlrBaseRet><vlrRetencao>" . $this->fmtVal($n['valor_retencao']) . "</vlrRetencao><vlrRetSub>0.00</vlrRetSub><vlrNRetPrinc>0.00</vlrNRetPrinc><vlrServicos15>" . $this->fmtVal($n['valor_bruto']) . "</vlrServicos15><vlrServicos20>0.00</vlrServicos20><vlrServicos25>0.00</vlrServicos25><vlrAdicional>0.00</vlrAdicional><vlrNRetAdwordc>0.00</vlrNRetAdwordc></nfs>\n";
            }
            $xml .= "                <idePrestServ><cnpjPrestador>{$cnpjP}</cnpjPrestador><vlrTotalBruto>" . $this->fmtVal($totBruto) . "</vlrTotalBruto><vlrTotalBaseRet>" . $this->fmtVal($totBruto) . "</vlrTotalBaseRet><vlrTotalRetPrinc>" . $this->fmtVal($totRet) . "</vlrTotalRetPrinc><vlrTotalRetAdic>0.00</vlrTotalRetAdic><vlrTotalNRetPrinc>0.00</vlrTotalNRetPrinc><vlrTotalNRetAdic>0.00</vlrTotalNRetAdic>\n{$nfsXml}                </idePrestServ>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstabObra><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpj}</nrInscEstab><indObra>0</indObra>\n{$xml}        </ideEstabObra>";

        return $this->envelope('evtServTom', 'evtTomadorServicos', $id, $body);
    }

    // ═══ R-2020 ═══════════════════════════════════════

    private function gerarR2020(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2020 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2020.");

        $porTom = [];
        foreach ($registros as $r) $porTom[preg_replace('/\D/', '', $r['cnpj_tomador'])][] = $r;

        $xml = '';
        foreach ($porTom as $cnpjT => $nfs) {
            $totBruto = array_sum(array_column($nfs, 'valor_bruto'));
            $totRet   = array_sum(array_column($nfs, 'valor_retencao'));
            $nfsXml = '';
            foreach ($nfs as $n) {
                $nfsXml .= "                    <nfs><serie>" . ($n['serie'] ?? '1') . "</serie><numDocto>" . ($n['num_documento'] ?: '1') . "</numDocto><dtEmissaoNF>" . ($n['data_emissao'] ?: date('Y-m-d')) . "</dtEmissaoNF><vlrBruto>" . $this->fmtVal($n['valor_bruto']) . "</vlrBruto><vlrBaseRet>" . $this->fmtVal($n['valor_bruto']) . "</vlrBaseRet><vlrRetencao>" . $this->fmtVal($n['valor_retencao']) . "</vlrRetencao><vlrRetSub>0.00</vlrRetSub><vlrNRetPrinc>0.00</vlrNRetPrinc><vlrServicos15>" . $this->fmtVal($n['valor_bruto']) . "</vlrServicos15><vlrServicos20>0.00</vlrServicos20><vlrServicos25>0.00</vlrServicos25><vlrAdicional>0.00</vlrAdicional><vlrNRetAdwordc>0.00</vlrNRetAdwordc></nfs>\n";
            }
            $xml .= "                <ideTomador><tpInscTomador>1</tpInscTomador><nrInscTomador>{$cnpjT}</nrInscTomador><vlrTotalBruto>" . $this->fmtVal($totBruto) . "</vlrTotalBruto><vlrTotalBaseRet>" . $this->fmtVal($totBruto) . "</vlrTotalBaseRet><vlrTotalRetPrinc>" . $this->fmtVal($totRet) . "</vlrTotalRetPrinc><vlrTotalRetAdic>0.00</vlrTotalRetAdic><vlrTotalNRetPrinc>0.00</vlrTotalNRetPrinc><vlrTotalNRetAdic>0.00</vlrTotalNRetAdic>\n{$nfsXml}                </ideTomador>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstabPrest><tpInscEstabPrest>1</tpInscEstabPrest><nrInscEstabPrest>{$cnpj}</nrInscEstabPrest>\n{$xml}        </ideEstabPrest>";

        return $this->envelope('evtServPrest', 'evtServicosPrestados', $id, $body);
    }

    // ═══ R-2060 ═══════════════════════════════════════

    private function gerarR2060(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r2060 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2060.");

        $xml = '';
        foreach ($registros as $r) {
            $bc = (float)$r['valor_rec_bruta'] - (float)$r['valor_exclusoes'];
            $cprb = $bc * ((float)$r['aliquota'] / 100);
            $xml .= "                <tipoCod><codAtivEcon>" . htmlspecialchars($r['cnae']) . "</codAtivEcon><vlrRecBrutaAtiv>" . $this->fmtVal($r['valor_rec_bruta']) . "</vlrRecBrutaAtiv><vlrExcRecBruta>" . $this->fmtVal($r['valor_exclusoes']) . "</vlrExcRecBruta><vlrAdicRecBruta>0.00</vlrAdicRecBruta><vlrBcCPRB>" . $this->fmtVal($bc) . "</vlrBcCPRB><vlrCPRBapur>" . $this->fmtVal($cprb) . "</vlrCPRBapur></tipoCod>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpj}</nrInscEstab>\n{$xml}        </ideEstab>";

        return $this->envelope('evtCPRB', 'evtCPRB', $id, $body);
    }

    // ═══ R-2099 ═══════════════════════════════════════

    private function gerarR2099(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideRespInf><nmResp>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmResp><cpfResp>" . substr($cnpj, 0, 11) . "</cpfResp><telefone></telefone><email></email></ideRespInf>\n"
              . "        <infoFech><evtServTm>S</evtServTm><evtServPr>S</evtServPr><evtAssDespRec>N</evtAssDespRec><evtAssDespRep>N</evtAssDespRep><evtComProd>N</evtComProd><evtCPRB>N</evtCPRB><evtAquis>N</evtAquis></infoFech>";

        return $this->envelope('evtFechaEvPer', 'evtFechaEvPer', $id, $body);
    }

    // ═══ R-4010 ═══════════════════════════════════════

    private function gerarR4010(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $stmt = $this->db->prepare("SELECT * FROM r4010 WHERE competencia_id = ?");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-4010.");

        $porB = [];
        foreach ($registros as $r) $porB[preg_replace('/\D/', '', $r['cpf_beneficiario'])][] = $r;

        $xml = '';
        foreach ($porB as $cpf => $pagtos) {
            $pgto = '';
            foreach ($pagtos as $p) {
                $pgto .= "                    <infoPgto><dtFG>{$p['data_pagamento']}</dtFG><vlrRendBruto>" . $this->fmtVal($p['valor_bruto']) . "</vlrRendBruto><vlrRendTrib>" . $this->fmtVal($p['valor_base_ir'] ?? $p['valor_bruto']) . "</vlrRendTrib><vlrIR>" . $this->fmtVal($p['valor_ir']) . "</vlrIR></infoPgto>\n";
            }
            $xml .= "                <ideBenef><cpfBenef>{$cpf}</cpfBenef><nmBenef>" . htmlspecialchars($pagtos[0]['nome_beneficiario'] ?? '') . "</nmBenef><ideEvtAdic><tpIsencao>0</tpIsencao><natRend>" . ($pagtos[0]['natureza_rendimento'] ?? '10001') . "</natRend></ideEvtAdic>\n{$pgto}                </ideBenef>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpj}</nrInscEstab>\n{$xml}        </ideEstab>";

        return $this->envelope('evtRetPF', 'evt4010PagtoBeneficiarioPF', $id, $body);
    }

    // ═══ R-4020 · Pagamentos PJ (estrutura oficial) ═══
      private function gerarR4020(array $comp): string
    {
        // Este método agora só gera o XML de UM beneficiário — o primeiro.
        // Para múltiplos, use gerarR4020PorBeneficiario() no controller.
        $arquivos = $this->gerarR4020PorBeneficiario($comp);
        if (empty($arquivos)) {
            throw new \RuntimeException("Nenhum registro R-4020 encontrado.");
        }
        return $arquivos[0];
    }

    public function gerarR4020PorBeneficiario(array $comp): array
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);

        $stmt = $this->db->prepare("SELECT * FROM r4020 WHERE competencia_id = ? ORDER BY cnpj_beneficiario, natureza_rendimento, data_pagamento");
        $stmt->execute([$comp['id']]);
        $registros = $stmt->fetchAll();
        if (empty($registros)) return [];

        // Agrupa por CNPJ beneficiário
        $porBenef = [];
        foreach ($registros as $r) {
            $porBenef[preg_replace('/\D/', '', $r['cnpj_beneficiario'])][] = $r;
        }

        $xmls = [];
        foreach ($porBenef as $cnpjBenef => $pagtosBenef) {
            $nome = $pagtosBenef[0]['razao_social_beneficiario'] ?? '';
            $id = $this->gerarId($cnpj);

            // Agrupa por natureza dentro deste beneficiário
            $porNat = [];
            foreach ($pagtosBenef as $p) {
                $nat = $p['cod_tipo_servico'] ?? $p['natureza_rendimento'] ?? '00000';
                $porNat[$nat][] = $p;
            }

            $idePgtoXml = '';
            foreach ($porNat as $natRend => $pagtos) {
                $infoPgtoXml = '';
                foreach ($pagtos as $p) {
                    $vBruto  = (float)($p['valor_bruto'] ?? 0);
                    $vBaseIR = (float)($p['valor_base_ir'] ?? $vBruto);
                    $vIR     = (float)($p['valor_ir'] ?? 0);
                    $vCSLL   = (float)($p['valor_csll'] ?? 0);
                    $vCofins = (float)($p['valor_cofins'] ?? 0);
                    $vPIS    = (float)($p['valor_pis'] ?? 0);
                    $indJud  = !empty($p['indicador_judicial']) ? 'S' : 'N';

                    $infoPgtoXml .= "<infoPgto>"
                                  . "<dtFG>{$p['data_pagamento']}</dtFG>"
                                  . "<vlrBruto>" . $this->fmtVal($vBruto) . "</vlrBruto>"
                                  . "<indJud>{$indJud}</indJud>";

                    if ($vIR > 0 || $vCSLL > 0 || $vCofins > 0 || $vPIS > 0) {
                        $infoPgtoXml .= "<retencoes>"
                                      . "<vlrBaseIR>" . $this->fmtVal($vBaseIR) . "</vlrBaseIR>"
                                      . "<vlrIR>" . $this->fmtVal($vIR) . "</vlrIR>";
                        if ($vCSLL > 0) {
                            $infoPgtoXml .= "<vlrBaseCSLL>" . $this->fmtVal($vBruto) . "</vlrBaseCSLL>"
                                          . "<vlrCSLL>" . $this->fmtVal($vCSLL) . "</vlrCSLL>";
                        }
                        if ($vCofins > 0) {
                            $infoPgtoXml .= "<vlrBaseCofins>" . $this->fmtVal($vBruto) . "</vlrBaseCofins>"
                                          . "<vlrCofins>" . $this->fmtVal($vCofins) . "</vlrCofins>";
                        }
                        if ($vPIS > 0) {
                            $infoPgtoXml .= "<vlrBasePP>" . $this->fmtVal($vBruto) . "</vlrBasePP>"
                                          . "<vlrPP>" . $this->fmtVal($vPIS) . "</vlrPP>";
                        }
                        $infoPgtoXml .= "</retencoes>";
                    }

                    $infoPgtoXml .= "</infoPgto>";
                }

                $idePgtoXml .= "<idePgto><natRend>{$natRend}</natRend>{$infoPgtoXml}</idePgto>";
            }

            $benefXml = "<ideBenef>"
                      . "<cnpjBenef>{$cnpjBenef}</cnpjBenef>"
                      . "<nmBenef>" . htmlspecialchars($nome) . "</nmBenef>"
                      . $idePgtoXml
                      . "</ideBenef>";

            $cnpjEstab = preg_replace('/\D/', '', $registros[0]['cnpj_contribuinte'] ?? $cnpj) ?: $cnpj;

            $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
                  . "        {$this->ideContri($cnpj)}\n"
                  . "        <ideEstab><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpjEstab}</nrInscEstab>{$benefXml}</ideEstab>";

            $xmls[] = $this->envelope('evtRetPJ', 'evt4020PagtoBeneficiarioPJ', $id, $body);
            usleep(1000); // garantir IDs únicos
        }

        return $xmls;
    }
   
    // ═══ R-4099 ═══════════════════════════════════════

    private function gerarR4099(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $this->nrRecibo)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideRespInf><nmResp>" . htmlspecialchars($comp['razao_social'] ?? '') . "</nmResp><cpfResp>" . substr($cnpj, 0, 11) . "</cpfResp><telefone></telefone><email></email></ideRespInf>\n"
              . "        <infoFech><fechRet>S</fechRet></infoFech>";

        return $this->envelope('evtFechaRetPgtos', 'evtFechaRetPgtos', $id, $body);
    }

    // ═══ R-9000 ═══════════════════════════════════════

    private function gerarR9000(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);
        $nrRecibo = $comp['num_recibo'] ?? '';
        $tpEvt    = $comp['tipo_evento_exclusao'] ?? 'R-2010';

        if (empty($nrRecibo)) throw new \RuntimeException("R-9000 exige número do recibo.");

        $body = "        <ideEvento><tpAmb>{$this->tpAmb}</tpAmb><procEmi>{$this->procEmi}</procEmi><verProc>{$this->verProc}</verProc></ideEvento>\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoExclusao><tpEvento>{$tpEvt}</tpEvento><nrRecEvt>{$nrRecibo}</nrRecEvt><perApur>{$comp['periodo']}</perApur></infoExclusao>";

        return $this->envelope('evtExclusao', 'evtExclusao', $id, $body);
    }
}