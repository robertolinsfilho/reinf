<?php

namespace App\Services;

use App\Repositories\ArquivoGeradoRepository;
use App\Repositories\EventoRepository;
use App\Repositories\ProcessoRepository;
use App\Services\ValidacaoService;

class GeracaoXmlService
{
    private string $outputDir;
    private int $tpAmb;
    private int $indRetif = 1;
    private ?string $nrRecibo = null;
    private string $verProc;
    private int $procEmi;
    private int $idSeq = 0;
    private EventoRepository $eventos;
    private ArquivoGeradoRepository $arquivos;
    private ProcessoRepository $processos;

    public function __construct(private \PDO $db)
    {
        $this->outputDir = storage_path('xml') . '/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $this->tpAmb   = (int) config('reinf.tp_amb', 2);
        $this->verProc = (string) config('reinf.ver_proc', 'EFD-REINF-WEB-1.0');
        $this->procEmi = (int) config('reinf.proc_emi', 1);
        $this->eventos   = new EventoRepository($db);
        $this->arquivos  = new ArquivoGeradoRepository($db);
        $this->processos = new ProcessoRepository($db);
    }

    public function gerar(array $competencia, array $eventos, int $indRetif = 1, ?string $nrRecibo = null): array
    {
        $arquivos = [];
        $this->indRetif = $indRetif;
        $this->nrRecibo = $nrRecibo;

        foreach ($eventos as $evento) {
            // R-4020 / R-2055: múltiplos XMLs (um por beneficiário / produtor)
            if ($evento === 'R4020') {
                $xmls = $this->gerarR4020PorBeneficiario($competencia);
                foreach ($xmls as $i => $xml) {
                    $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif, $i);
                }
                continue;
            }
            if ($evento === 'R2055') {
                $xmls = $this->gerarR2055PorProdutor($competencia);
                foreach ($xmls as $i => $xml) {
                    $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif, $i);
                }
                continue;
            }
            if ($evento === 'R2010') {
                $xmls = $this->gerarR2010PorPrestador($competencia);
                foreach ($xmls as $i => $xml) {
                    $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif, $i);
                }
                continue;
            }
            if ($evento === 'R2020') {
                $xmls = $this->gerarR2020PorTomador($competencia);
                foreach ($xmls as $i => $xml) {
                    $arquivos[] = $this->salvarXml($evento, $competencia, $xml, $indRetif, $i);
                }
                continue;
            }

            $xml = match ($evento) {
                'R1000' => $this->gerarR1000($competencia),
                'R1070' => $this->gerarR1070($competencia),
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
        // Manual RFB: ID + tpInsc(1) + nrInsc(14) + AAAAMMDDHHMMSS + seq(5) = 36 chars
        // CNPJ: raiz 8 + zeros à direita até 14 (deve coincidir com ideContri — MS1010)
        [$tp, $nrInsc] = $this->tpENrInscContribuinte($cnpj);
        $nrId = $tp === '1'
            ? str_pad($nrInsc, 14, '0', STR_PAD_RIGHT)
            : str_pad($nrInsc, 14, '0', STR_PAD_LEFT);

        $this->idSeq = ($this->idSeq + 1) % 100000;
        return 'ID' . $tp . $nrId . date('YmdHis') . str_pad((string) $this->idSeq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * nrInsc do contribuinte no evento/lote (Manual do Desenvolvedor):
     * CNPJ = raiz/base 8 dígitos (exceto Adm. Pública Direta Federal — não tratado aqui).
     * CPF = 11 dígitos.
     *
     * @return array{0: string, 1: string} [tpInsc, nrInsc]
     */
    private function tpENrInscContribuinte(string $inscricao, ?string $tpInsc = null): array
    {
        $nr = preg_replace('/\D/', '', $inscricao) ?? '';
        $tp = $tpInsc ?? (strlen($nr) <= 11 ? '2' : '1');
        if ($tp === '1') {
            $nr = substr(str_pad($nr, 8, '0', STR_PAD_LEFT), 0, 8);
        } else {
            $nr = substr(str_pad($nr, 11, '0', STR_PAD_LEFT), 0, 11);
        }
        return [$tp, $nr];
    }

    private function ideEvento(string $perApur, int $indRetif = 1, ?string $nrRecibo = null): string
    {
        $xml  = "<ideEvento>\n";
        $xml .= "            <indRetif>{$indRetif}</indRetif>\n";
        if ($indRetif === 2 && $nrRecibo) {
            $nrRecibo = preg_replace('/[^A-Za-z0-9.\\-\/]/', '', $nrRecibo) ?? '';
            $nrRecibo = substr($nrRecibo, 0, 52);
            if ($nrRecibo !== '') {
                $xml .= '            <nrRecibo>' . htmlspecialchars($nrRecibo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</nrRecibo>\n";
            }
        }
        $xml .= "            <perApur>{$perApur}</perApur>\n";
        $xml .= "            <tpAmb>{$this->tpAmb}</tpAmb>\n";
        $xml .= "            <procEmi>{$this->procEmi}</procEmi>\n";
        $xml .= "            <verProc>{$this->verProc}</verProc>\n";
        $xml .= "        </ideEvento>";
        return $xml;
    }

    /** ideEvento dos eventos de tabela (R-1000 / R-1070): só tpAmb, procEmi, verProc. */
    private function ideEventoTabela(): string
    {
        return "<ideEvento>\n"
             . "            <tpAmb>{$this->tpAmb}</tpAmb>\n"
             . "            <procEmi>{$this->procEmi}</procEmi>\n"
             . "            <verProc>{$this->verProc}</verProc>\n"
             . "        </ideEvento>";
    }

    private function ideContri(string $cnpj, ?string $tpInsc = null): string
    {
        [$tp, $nr] = $this->tpENrInscContribuinte($cnpj, $tpInsc);
        return "<ideContri>\n            <tpInsc>{$tp}</tpInsc>\n            <nrInsc>{$nr}</nrInsc>\n        </ideContri>";
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
        // XSD EFD-Reinf (TS_valorMonetario): separador decimal é vírgula (ex: 1234,56)
        return number_format((float) ($valor ?? 0), 2, ',', '');
    }

    // ═══ R-1000 ═══════════════════════════════════════

    private function gerarR1000(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', (string) ($comp['cnpj'] ?? '')) ?? '';
        $tpInsc = (string) ($comp['tipo_contribuinte'] ?? (strlen($cnpj) <= 11 ? '2' : '1'));
        $id   = $this->gerarId($cnpj);

        $classif = str_pad(preg_replace('/\D/', '', (string) ($comp['classificacao_tributos'] ?? '99')) ?? '99', 2, '0', STR_PAD_LEFT);
        $classTribMap = config('reinf.class_trib', []);
        if (!is_array($classTribMap) || !array_key_exists($classif, $classTribMap)) {
            throw new \RuntimeException('Classificação tributária inválida (Tabela 08). Atualize o contribuinte.');
        }

        $nomeContato = trim(html_entity_decode((string) ($comp['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $cpfContato  = preg_replace('/\D/', '', (string) ($comp['cpf_contato'] ?? '')) ?? '';

        if ($nomeContato === '' || strlen($cpfContato) !== 11) {
            throw new \RuntimeException(
                'Cadastre nome e CPF do contato no contribuinte antes de gerar o R-1000.'
            );
        }

        if (!ValidacaoService::validarCpf($cpfContato)) {
            throw new \RuntimeException('CPF do contato inválido. Atualize o contribuinte.');
        }

        $email    = trim((string) ($comp['email'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string) ($comp['telefone'] ?? '')) ?? '';
        if (strlen($telefone) < 10 || strlen($telefone) > 13) {
            throw new \RuntimeException('Cadastre telefone com DDD (mín. 10 dígitos) no contribuinte.');
        }

        $indEscrit = (int) ($comp['ind_escrituracao'] ?? 0);
        $indDeson  = (int) ($comp['ind_desoneracao'] ?? 0);
        $indAcordo = (int) ($comp['ind_acordo_isen_multa'] ?? 0);
        $indSitPj  = (int) ($comp['ind_sit_pj'] ?? 0);

        if ($indDeson === 1 && !in_array($classif, ['02', '03', '99'], true)) {
            throw new \RuntimeException('Desoneração da folha só é permitida com classTrib 02, 03 ou 99.');
        }
        if ($indAcordo === 1 && $classif !== '60') {
            throw new \RuntimeException('Acordo de isenção de multa só é permitido com classTrib 60.');
        }

        // Contato: foneCel (11 dígitos) ou foneFixo (10); e-mail opcional
        $contatoXml = "                    <contato>\n"
                    . "                        <nmCtt>" . htmlspecialchars($nomeContato, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</nmCtt>\n"
                    . "                        <cpfCtt>{$cpfContato}</cpfCtt>\n";
        if (strlen($telefone) >= 11) {
            $contatoXml .= "                        <foneCel>{$telefone}</foneCel>\n";
        } else {
            $contatoXml .= "                        <foneFixo>{$telefone}</foneFixo>\n";
        }
        if ($email !== '') {
            $contatoXml .= "                        <email>" . htmlspecialchars($email, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</email>\n";
        }
        $contatoXml .= "                    </contato>\n";

        $softXml = $this->montarSoftHouseXml();

        $infoCadastro = "                <infoCadastro>\n"
                      . "                    <classTrib>{$classif}</classTrib>\n"
                      . "                    <indEscrituracao>{$indEscrit}</indEscrituracao>\n"
                      . "                    <indDesoneracao>{$indDeson}</indDesoneracao>\n"
                      . "                    <indAcordoIsenMulta>{$indAcordo}</indAcordoIsenMulta>\n";
        if ($tpInsc === '1') {
            $infoCadastro .= "                    <indSitPJ>{$indSitPj}</indSitPJ>\n";
        }
        $infoCadastro .= $contatoXml . $softXml . "                </infoCadastro>\n";

        $iniValid = $comp['periodo']; // AAAA-MM da competência = início de validade do R-1000

        $body = "        {$this->ideEventoTabela()}\n"
              . "        {$this->ideContri($cnpj, $tpInsc)}\n"
              . "        <infoContri>\n"
              . "            <inclusao>\n"
              . "                <idePeriodo><iniValid>{$iniValid}</iniValid></idePeriodo>\n"
              . $infoCadastro
              . "            </inclusao>\n"
              . "        </infoContri>";

        return $this->envelope('evtInfoContri', 'evtInfoContribuinte', $id, $body);
    }

    private function montarSoftHouseXml(): string
    {
        $sh = (array) config('reinf.softhouse', []);
        $cnpj = preg_replace('/\D/', '', (string) ($sh['cnpj'] ?? '')) ?? '';
        $razao = trim((string) ($sh['razao'] ?? ''));
        $contato = trim((string) ($sh['contato'] ?? ''));
        if ($cnpj === '' || strlen($cnpj) !== 14 || $razao === '' || $contato === '') {
            return '';
        }

        $xml = "                    <softHouse>\n"
             . "                        <cnpjSoftHouse>{$cnpj}</cnpjSoftHouse>\n"
             . "                        <nmRazao>" . htmlspecialchars($razao, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</nmRazao>\n"
             . "                        <nmCont>" . htmlspecialchars($contato, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</nmCont>\n";
        $tel = preg_replace('/\D/', '', (string) ($sh['telefone'] ?? '')) ?? '';
        if (strlen($tel) >= 10) {
            $xml .= "                        <telefone>{$tel}</telefone>\n";
        }
        $email = trim((string) ($sh['email'] ?? ''));
        if ($email !== '') {
            $xml .= "                        <email>" . htmlspecialchars($email, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</email>\n";
        }
        $xml .= "                    </softHouse>\n";
        return $xml;
    }

    // ═══ R-1070 ═══════════════════════════════════════

    private function gerarR1070(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $processos = $this->processos->listAtivosByContribuinte((int) $comp['contribuinte_id']);

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

        $body = "        {$this->ideEventoTabela()}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoProcesso>\n" . $processosXml . "        </infoProcesso>";

        return $this->envelope('evtTabProcesso', 'evtTabProcesso', $id, $body);
    }

    // ═══ R-2010 ═══════════════════════════════════════

    /**
     * R-2010: um XML por prestador (XSD: idePrestServ ocorre 1-1 em ideEstabObra).
     *
     * @return list<string>
     */
    private function gerarR2010PorPrestador(array $comp): array
    {
        $cnpj = preg_replace('/\D/', '', (string) $comp['cnpj']) ?? '';

        $registros = $this->eventos->listarParaGeracao('r2010', (int) $comp['id'], 'cnpj_prestador, data_emissao, id');
        if (empty($registros)) {
            throw new \RuntimeException('Nenhum registro R-2010.');
        }

        $reciboPadrao = $this->nrRecibo;
        if ($this->indRetif === 2 && !$reciboPadrao) {
            $reciboPadrao = $this->ultimoReciboEvento((int) $comp['id'], 'R2010');
        }

        $porPrest = [];
        foreach ($registros as $r) {
            $cnpjP = preg_replace('/\D/', '', (string) ($r['cnpj_prestador'] ?? '')) ?? '';
            if ($cnpjP === '') {
                continue;
            }
            $porPrest[$cnpjP][] = $r;
        }
        if (empty($porPrest)) {
            throw new \RuntimeException('Nenhum prestador válido no R-2010.');
        }

        $xmls = [];
        foreach ($porPrest as $cnpjP => $nfs) {
            $id = $this->gerarId($cnpj);

            $totBruto = 0.0;
            $totBase  = 0.0;
            $totRet   = 0.0;
            $indCprb  = in_array((string) ($nfs[0]['ind_cprb'] ?? '0'), ['0', '1'], true)
                ? (string) $nfs[0]['ind_cprb']
                : '0';

            $nfsXml = '';
            $chavesNfs = [];
            foreach ($nfs as $n) {
                // Leiaute: vlrBruto deve ser > 0 (MS0030 em valores negativos)
                $vBruto = (float) ($n['valor_bruto'] ?? 0);
                if ($vBruto <= 0) {
                    continue;
                }
                $vBase  = (float) ($n['valor_base_retencao'] ?? 0);
                if ($vBase <= 0) {
                    $vBase = $vBruto;
                }
                $vRet = (float) ($n['valor_retencao'] ?? 0);
                if ($vRet < 0) {
                    continue;
                }
                if ($vRet == 0.0 && $vBase > 0) {
                    $aliq = $indCprb === '1' ? 0.035 : 0.11;
                    $vRet = round($vBase * $aliq, 2);
                }

                $tpServ = preg_replace('/\D/', '', (string) ($n['cod_servico'] ?? '')) ?? '';
                if ($tpServ === '') {
                    $tpServ = '100000001';
                }
                $tpServ = str_pad(substr($tpServ, 0, 9), 9, '0', STR_PAD_LEFT);

                $serie    = trim((string) ($n['serie'] ?? ''));
                $serie    = $serie !== '' ? $serie : '0';
                $numDocto = trim((string) ($n['num_documento'] ?? ''));
                $numDocto = $numDocto !== '' ? $numDocto : '1';
                $dtEm     = $n['data_emissao'] ?: ($comp['periodo'] . '-01');

                // MS1076: serie+numDocto deve ser único dentro do evento
                $chaveNfs = $serie . '|' . $numDocto;
                if (isset($chavesNfs[$chaveNfs])) {
                    $prev = $chavesNfs[$chaveNfs];
                    // Linha idêntica (reimport): ignora
                    if (
                        abs($prev['bruto'] - $vBruto) < 0.005
                        && abs($prev['ret'] - $vRet) < 0.005
                        && $prev['dt'] === $dtEm
                        && $prev['tp'] === $tpServ
                    ) {
                        continue;
                    }
                    $sufixo = (string) (int) ($n['id'] ?? 0);
                    if ($sufixo === '0') {
                        $sufixo = (string) (count($chavesNfs) + 1);
                    }
                    $base = substr($numDocto, 0, max(1, 15 - 1 - strlen($sufixo)));
                    $numDocto = $base . '-' . $sufixo;
                    $chaveNfs = $serie . '|' . $numDocto;
                }
                $chavesNfs[$chaveNfs] = [
                    'bruto' => $vBruto,
                    'ret'   => $vRet,
                    'dt'    => $dtEm,
                    'tp'    => $tpServ,
                ];

                $totBruto += $vBruto;
                $totBase  += $vBase;
                $totRet   += $vRet;

                $nfsXml .= "                    <nfs>\n"
                         . '                        <serie>' . htmlspecialchars($serie, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</serie>\n"
                         . '                        <numDocto>' . htmlspecialchars($numDocto, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</numDocto>\n"
                         . "                        <dtEmissaoNF>{$dtEm}</dtEmissaoNF>\n"
                         . '                        <vlrBruto>' . $this->fmtVal($vBruto) . "</vlrBruto>\n"
                         . "                        <infoTpServ>\n"
                         . "                            <tpServico>{$tpServ}</tpServico>\n"
                         . '                            <vlrBaseRet>' . $this->fmtVal($vBase) . "</vlrBaseRet>\n"
                         . '                            <vlrRetencao>' . $this->fmtVal($vRet) . "</vlrRetencao>\n"
                         . "                        </infoTpServ>\n"
                         . "                    </nfs>\n";
            }

            if ($nfsXml === '') {
                continue;
            }

            $reciboEvt = $reciboPadrao;
            if ($this->indRetif === 2 && count($porPrest) === 1 && $reciboPadrao) {
                $reciboEvt = $reciboPadrao;
            }

            $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboEvt)}\n"
                  . "        {$this->ideContri($cnpj)}\n"
                  . "        <infoServTom>\n"
                  . "            <ideEstabObra>\n"
                  . "                <tpInscEstab>1</tpInscEstab>\n"
                  . "                <nrInscEstab>{$cnpj}</nrInscEstab>\n"
                  . "                <indObra>0</indObra>\n"
                  . "                <idePrestServ>\n"
                  . "                    <cnpjPrestador>{$cnpjP}</cnpjPrestador>\n"
                  . '                    <vlrTotalBruto>' . $this->fmtVal($totBruto) . "</vlrTotalBruto>\n"
                  . '                    <vlrTotalBaseRet>' . $this->fmtVal($totBase) . "</vlrTotalBaseRet>\n"
                  . '                    <vlrTotalRetPrinc>' . $this->fmtVal($totRet) . "</vlrTotalRetPrinc>\n"
                  . "                    <indCPRB>{$indCprb}</indCPRB>\n"
                  . $nfsXml
                  . "                </idePrestServ>\n"
                  . "            </ideEstabObra>\n"
                  . "        </infoServTom>";

            $xmls[] = $this->envelope('evtServTom', 'evtTomadorServicos', $id, $body);
        }

        if (empty($xmls)) {
            throw new \RuntimeException('Nenhuma NF válida no R-2010 (vlrBruto deve ser > 0).');
        }

        return $xmls;
    }

    /**
     * R-2055: um XML por estabelecimento adquirente + produtor (leiaute oficial).
     *
     * @return list<string>
     */
    public function gerarR2055PorProdutor(array $comp): array
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);

        $registros = $this->eventos->listarParaGeracao(
            'r2055',
            (int) $comp['id'],
            'nr_insc_adquirente, nr_insc_produtor, ind_aquis, id'
        );
        if (empty($registros)) {
            return [];
        }

        $recibosPorChave = $this->mapRecibosR2055((int) $comp['id']);

        // Agrupa por adquirente + produtor
        $grupos = [];
        foreach ($registros as $r) {
            $chave = $r['nr_insc_adquirente'] . '|' . $r['nr_insc_produtor'];
            $grupos[$chave][] = $r;
        }

        $xmls = [];
        foreach ($grupos as $chave => $linhas) {
            $first = $linhas[0];
            $tpAdq = (string) ($first['tp_insc_adquirente'] ?? '1');
            $nrAdq = preg_replace('/\D/', '', (string) $first['nr_insc_adquirente']);
            $tpProd = (string) ($first['tp_insc_produtor'] ?? '1');
            $nrProd = preg_replace('/\D/', '', (string) $first['nr_insc_produtor']);

            $reciboEvt = $this->nrRecibo;
            if ($this->indRetif === 2 && isset($recibosPorChave[$chave])) {
                $reciboEvt = $recibosPorChave[$chave];
            }

            // Soma por indAquis (até 6 ocorrências)
            $porInd = [];
            foreach ($linhas as $r) {
                $ind = (string) ($r['ind_aquis'] ?? '1');
                if (!isset($porInd[$ind])) {
                    $porInd[$ind] = [
                        'bruto' => 0.0,
                        'cp'    => 0.0,
                        'rat'   => 0.0,
                        'senar' => 0.0,
                    ];
                }
                $porInd[$ind]['bruto'] += (float) $r['valor_bruto'];
                $porInd[$ind]['cp']    += (float) $r['valor_cp_desc'];
                $porInd[$ind]['rat']   += (float) $r['valor_rat_desc'];
                $porInd[$ind]['senar'] += (float) $r['valor_senar_desc'];
            }

            $detXml = '';
            foreach ($porInd as $ind => $v) {
                $detXml .= '                    <detAquis>'
                         . "<indAquis>{$ind}</indAquis>"
                         . '<vlrBruto>' . $this->fmtVal($v['bruto']) . '</vlrBruto>'
                         . '<vlrCPDescPR>' . $this->fmtVal($v['cp']) . '</vlrCPDescPR>'
                         . '<vlrRatDescPR>' . $this->fmtVal($v['rat']) . '</vlrRatDescPR>'
                         . '<vlrSenarDesc>' . $this->fmtVal($v['senar']) . '</vlrSenarDesc>'
                         . "</detAquis>\n";
            }

            $indOpc = strtoupper(trim((string) ($first['ind_opc_cp'] ?? '')));
            $opcXml = ($indOpc === 'S') ? '<indOpcCP>S</indOpcCP>' : '';

            $id = $this->gerarId($cnpj);
            $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboEvt)}\n"
                  . "        {$this->ideContri($cnpj)}\n"
                  . "        <infoAquisProd>\n"
                  . "            <ideEstabAdquir>"
                  . "<tpInscAdq>{$tpAdq}</tpInscAdq>"
                  . "<nrInscAdq>{$nrAdq}</nrInscAdq>\n"
                  . "                <ideProdutor>"
                  . "<tpInscProd>{$tpProd}</tpInscProd>"
                  . "<nrInscProd>{$nrProd}</nrInscProd>"
                  . $opcXml . "\n"
                  . $detXml
                  . "                </ideProdutor>\n"
                  . "            </ideEstabAdquir>\n"
                  . "        </infoAquisProd>";

            $xmls[] = $this->envelope('evtAqProd', 'evt2055AquisicaoProdRural', $id, $body);
        }

        return $xmls;
    }

    /** Mapa adquirente|produtor => recibo R-2055 consultado. */
    private function mapRecibosR2055(int $competenciaId): array
    {
        $map = [];
        foreach ($this->arquivos->listXmlsComRecibo($competenciaId, 'R2055') as $row) {
            $xml = (string) ($row['xml_conteudo'] ?? '');
            if (
                $xml === ''
                || !preg_match('/<nrInscAdq>(\d+)<\/nrInscAdq>/', $xml, $mAdq)
                || !preg_match('/<nrInscProd>(\d+)<\/nrInscProd>/', $xml, $mProd)
            ) {
                continue;
            }
            $chave = $mAdq[1] . '|' . $mProd[1];
            if (!isset($map[$chave])) {
                $map[$chave] = $row['nr_recibo_retornado'];
            }
        }
        return $map;
    }

    // ═══ R-2020 ═══════════════════════════════════════

    /**
     * R-2020: um XML por tomador (XSD: ideTomador ocorre 1-1 em ideEstabPrest).
     *
     * @return list<string>
     */
    private function gerarR2020PorTomador(array $comp): array
    {
        $cnpj = preg_replace('/\D/', '', (string) $comp['cnpj']) ?? '';

        $registros = $this->eventos->listarParaGeracao('r2020', (int) $comp['id'], 'cnpj_tomador, data_emissao, id');
        if (empty($registros)) {
            throw new \RuntimeException('Nenhum registro R-2020.');
        }

        $reciboPadrao = $this->nrRecibo;
        if ($this->indRetif === 2 && !$reciboPadrao) {
            $reciboPadrao = $this->ultimoReciboEvento((int) $comp['id'], 'R2020');
        }

        $porTom = [];
        foreach ($registros as $r) {
            $cnpjT = preg_replace('/\D/', '', (string) ($r['cnpj_tomador'] ?? '')) ?? '';
            if ($cnpjT === '') {
                continue;
            }
            $porTom[$cnpjT][] = $r;
        }
        if (empty($porTom)) {
            throw new \RuntimeException('Nenhum tomador válido no R-2020.');
        }

        $xmls = [];
        foreach ($porTom as $cnpjT => $nfs) {
            $tpTom = (string) ($nfs[0]['tipo_insc_tomador'] ?? '1');
            if (!in_array($tpTom, ['1', '4'], true)) {
                $tpTom = '1';
            }

            $totBruto = 0.0;
            $totBase  = 0.0;
            $totRet   = 0.0;
            $nfsXml   = '';
            $chavesNfs = [];

            foreach ($nfs as $n) {
                $vBruto = (float) ($n['valor_bruto'] ?? 0);
                if ($vBruto <= 0) {
                    continue;
                }
                $vBase = (float) ($n['valor_base_retencao'] ?? 0);
                if ($vBase <= 0) {
                    $vBase = $vBruto;
                }
                $vRet = (float) ($n['valor_retencao'] ?? 0);
                if ($vRet < 0) {
                    continue;
                }

                $serie    = trim((string) ($n['serie'] ?? ''));
                $serie    = $serie !== '' ? $serie : '0';
                $numDocto = trim((string) ($n['num_documento'] ?? ''));
                $numDocto = $numDocto !== '' ? $numDocto : '1';
                $dtEm     = $n['data_emissao'] ?: ($comp['periodo'] . '-01');

                // MS1076: serie+numDocto único no evento
                $chaveNfs = $serie . '|' . $numDocto;
                if (isset($chavesNfs[$chaveNfs])) {
                    $prev = $chavesNfs[$chaveNfs];
                    if (
                        abs($prev['bruto'] - $vBruto) < 0.005
                        && abs($prev['ret'] - $vRet) < 0.005
                        && $prev['dt'] === $dtEm
                    ) {
                        continue;
                    }
                    $sufixo = (string) (int) ($n['id'] ?? 0);
                    if ($sufixo === '0') {
                        $sufixo = (string) (count($chavesNfs) + 1);
                    }
                    $base = substr($numDocto, 0, max(1, 15 - 1 - strlen($sufixo)));
                    $numDocto = $base . '-' . $sufixo;
                    $chaveNfs = $serie . '|' . $numDocto;
                }
                $chavesNfs[$chaveNfs] = ['bruto' => $vBruto, 'ret' => $vRet, 'dt' => $dtEm];

                $totBruto += $vBruto;
                $totBase  += $vBase;
                $totRet   += $vRet;

                // Campos de retenção ficam em infoTpServ (não direto em nfs)
                $nfsXml .= "                    <nfs>\n"
                         . '                        <serie>' . htmlspecialchars($serie, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</serie>\n"
                         . '                        <numDocto>' . htmlspecialchars($numDocto, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</numDocto>\n"
                         . "                        <dtEmissaoNF>{$dtEm}</dtEmissaoNF>\n"
                         . '                        <vlrBruto>' . $this->fmtVal($vBruto) . "</vlrBruto>\n"
                         . "                        <infoTpServ>\n"
                         . "                            <tpServico>100000001</tpServico>\n"
                         . '                            <vlrBaseRet>' . $this->fmtVal($vBase) . "</vlrBaseRet>\n"
                         . '                            <vlrRetencao>' . $this->fmtVal($vRet) . "</vlrRetencao>\n"
                         . "                        </infoTpServ>\n"
                         . "                    </nfs>\n";
            }

            if ($nfsXml === '') {
                continue;
            }

            $id = $this->gerarId($cnpj);
            $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboPadrao)}\n"
                  . "        {$this->ideContri($cnpj)}\n"
                  . "        <infoServPrest>\n"
                  . "            <ideEstabPrest>\n"
                  . "                <tpInscEstabPrest>1</tpInscEstabPrest>\n"
                  . "                <nrInscEstabPrest>{$cnpj}</nrInscEstabPrest>\n"
                  . "                <ideTomador>\n"
                  . "                    <tpInscTomador>{$tpTom}</tpInscTomador>\n"
                  . "                    <nrInscTomador>{$cnpjT}</nrInscTomador>\n"
                  . "                    <indObra>0</indObra>\n"
                  . '                    <vlrTotalBruto>' . $this->fmtVal($totBruto) . "</vlrTotalBruto>\n"
                  . '                    <vlrTotalBaseRet>' . $this->fmtVal($totBase) . "</vlrTotalBaseRet>\n"
                  . '                    <vlrTotalRetPrinc>' . $this->fmtVal($totRet) . "</vlrTotalRetPrinc>\n"
                  . $nfsXml
                  . "                </ideTomador>\n"
                  . "            </ideEstabPrest>\n"
                  . "        </infoServPrest>";

            $xmls[] = $this->envelope('evtServPrest', 'evtServicosPrestados', $id, $body);
        }

        if (empty($xmls)) {
            throw new \RuntimeException('Nenhuma NF válida no R-2020 (vlrBruto deve ser > 0).');
        }

        return $xmls;
    }

    // ═══ R-2060 ═══════════════════════════════════════

    private function gerarR2060(array $comp): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);

        $registros = $this->eventos->listarParaGeracao('r2060', (int) $comp['id'], 'id ASC');
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-2060.");

        $reciboEvt = $this->nrRecibo;
        if ($this->indRetif === 2 && !$reciboEvt) {
            $reciboEvt = $this->ultimoReciboEvento((int) $comp['id'], 'R2060');
        }

        $xml = '';
        foreach ($registros as $r) {
            $bc = (float)$r['valor_rec_bruta'] - (float)$r['valor_exclusoes'];
            $cprb = $bc * ((float)$r['aliquota'] / 100);
            $xml .= "                <tipoCod><codAtivEcon>" . htmlspecialchars($r['cnae']) . "</codAtivEcon><vlrRecBrutaAtiv>" . $this->fmtVal($r['valor_rec_bruta']) . "</vlrRecBrutaAtiv><vlrExcRecBruta>" . $this->fmtVal($r['valor_exclusoes']) . "</vlrExcRecBruta><vlrAdicRecBruta>" . $this->fmtVal(0) . "</vlrAdicRecBruta><vlrBcCPRB>" . $this->fmtVal($bc) . "</vlrBcCPRB><vlrCPRBapur>" . $this->fmtVal($cprb) . "</vlrCPRBapur></tipoCod>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboEvt)}\n"
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

        $registros = $this->eventos->listarParaGeracao('r4010', (int) $comp['id'], 'id ASC');
        if (empty($registros)) throw new \RuntimeException("Nenhum registro R-4010.");

        $porB = [];
        foreach ($registros as $r) $porB[preg_replace('/\D/', '', $r['cpf_beneficiario'])][] = $r;

        $recibosPorCpf = $this->mapRecibosR4010((int) $comp['id']);
        $reciboPadrao  = $this->nrRecibo;
        if ($this->indRetif === 2 && !$reciboPadrao) {
            $reciboPadrao = $this->ultimoReciboEvento((int) $comp['id'], 'R4010');
        }

        // R-4010: um XML por competência (mesmo envelope); recibo único do evento
        $reciboEvt = $reciboPadrao;
        if ($this->indRetif === 2 && count($porB) === 1) {
            $cpfUnico = array_key_first($porB);
            if (isset($recibosPorCpf[$cpfUnico])) {
                $reciboEvt = $recibosPorCpf[$cpfUnico];
            }
        }

        $xml = '';
        foreach ($porB as $cpf => $pagtos) {
            $pgto = '';
            foreach ($pagtos as $p) {
                $pgto .= "                    <infoPgto><dtFG>{$p['data_pagamento']}</dtFG><vlrRendBruto>" . $this->fmtVal($p['valor_bruto']) . "</vlrRendBruto><vlrRendTrib>" . $this->fmtVal($p['valor_base_ir'] ?? $p['valor_bruto']) . "</vlrRendTrib><vlrIR>" . $this->fmtVal($p['valor_ir']) . "</vlrIR></infoPgto>\n";
            }
            $xml .= "                <ideBenef><cpfBenef>{$cpf}</cpfBenef><nmBenef>" . htmlspecialchars($pagtos[0]['nome_beneficiario'] ?? '') . "</nmBenef><ideEvtAdic><tpIsencao>0</tpIsencao><natRend>" . ($pagtos[0]['natureza_rendimento'] ?? '10001') . "</natRend></ideEvtAdic>\n{$pgto}                </ideBenef>\n";
        }

        $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboEvt)}\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <ideEstab><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpj}</nrInscEstab>\n{$xml}        </ideEstab>";

        return $this->envelope('evtRetPF', 'evt4010PagtoBeneficiarioPF', $id, $body);
    }

    // ═══ R-4020 · Pagamentos PJ (estrutura oficial) ═══

    public function gerarR4020PorBeneficiario(array $comp): array
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);

        $registros = $this->eventos->listarParaGeracao(
            'r4020',
            (int) $comp['id'],
            'cnpj_beneficiario, natureza_rendimento, data_pagamento'
        );
        if (empty($registros)) return [];

        // Recibos anteriores por beneficiário (retificação)
        $recibosPorChave = $this->mapRecibosR4020((int) $comp['id']);

        // Agrupa por CNPJ beneficiário + identificador adicional (ideEvtAdic)
        $porBenef = [];
        foreach ($registros as $r) {
            $cnpjBenef = preg_replace('/\D/', '', $r['cnpj_beneficiario']);
            $ideAdic   = trim((string) ($r['identificador_adicional'] ?? ''));
            $porBenef[$cnpjBenef . '|' . $ideAdic][] = $r;
        }

        $xmls = [];
        foreach ($porBenef as $chave => $pagtosBenef) {
            [$cnpjBenef, $ideAdic] = array_pad(explode('|', $chave, 2), 2, '');
            $nome = $pagtosBenef[0]['razao_social_beneficiario'] ?? '';
            $id = $this->gerarId($cnpj);

            $reciboEvt = $this->nrRecibo;
            if ($this->indRetif === 2 && isset($recibosPorChave[$chave])) {
                $reciboEvt = $recibosPorChave[$chave];
            }

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
                    $vBruto  = (float) ($p['valor_bruto'] ?? 0);
                    $vBaseIR = (float) ($p['valor_base_ir'] ?? $vBruto);
                    $vIR     = (float) ($p['valor_ir'] ?? 0);
                    $vCSLL   = (float) ($p['valor_csll'] ?? 0);
                    $vCofins = (float) ($p['valor_cofins'] ?? 0);
                    $vPIS    = (float) ($p['valor_pis'] ?? 0);
                    $vAgreg  = (float) ($p['vl_csrf_agregado'] ?? 0);
                    $vBaseCSLL   = (float) ($p['valor_base_csll'] ?? 0) ?: $vBruto;
                    $vBaseCofins = (float) ($p['valor_base_cofins'] ?? 0) ?: $vBruto;
                    $vBasePIS    = (float) ($p['valor_base_pis'] ?? 0) ?: $vBruto;
                    $vBaseAgreg  = (float) ($p['valor_base_agreg'] ?? 0) ?: $vBruto;
                    $indJud  = !empty($p['indicador_judicial']) ? 'S' : 'N';
                    $codPais = preg_replace('/\D/', '', (string) ($p['cod_pais'] ?? ''));
                    // 105 = Brasil — não informar paisResidExt
                    if ($codPais === '105') {
                        $codPais = '';
                    }

                    $infoPgtoXml .= '<infoPgto>'
                                  . '<dtFG>' . ($p['data_pagamento'] ?? '') . '</dtFG>'
                                  . '<vlrBruto>' . $this->fmtVal($vBruto) . '</vlrBruto>';

                    $indFci = (string) ($p['indicador_fci_scp'] ?? '');
                    if (in_array($indFci, ['1', '2'], true)) {
                        $cnpjFci = preg_replace('/\D/', '', (string) ($p['cnpj_fci_scp'] ?? ''));
                        $infoPgtoXml .= "<indFciScp>{$indFci}</indFciScp>";
                        if ($cnpjFci !== '') {
                            $infoPgtoXml .= '<nrInscFciScp>' . $cnpjFci . '</nrInscFciScp>';
                        }
                        if ($indFci === '2' && isset($p['percentual_scp']) && $p['percentual_scp'] !== null && $p['percentual_scp'] !== '') {
                            $infoPgtoXml .= '<percSCP>' . $this->fmtVal($p['percentual_scp']) . '</percSCP>';
                        }
                    }

                    $infoPgtoXml .= "<indJud>{$indJud}</indJud>";

                    if ($codPais !== '') {
                        $infoPgtoXml .= '<paisResidExt>' . htmlspecialchars($codPais) . '</paisResidExt>';
                    }

                    if (!empty($p['observacoes'])) {
                        $infoPgtoXml .= '<observ>' . htmlspecialchars(mb_substr((string) $p['observacoes'], 0, 200)) . '</observ>';
                    }

                    if ($vIR > 0 || $vCSLL > 0 || $vCofins > 0 || $vPIS > 0 || $vAgreg > 0) {
                        $infoPgtoXml .= '<retencoes>';
                        if ($vIR > 0) {
                            $infoPgtoXml .= '<vlrBaseIR>' . $this->fmtVal($vBaseIR) . '</vlrBaseIR>'
                                          . '<vlrIR>' . $this->fmtVal($vIR) . '</vlrIR>';
                        }
                        if ($vAgreg > 0) {
                            $infoPgtoXml .= '<vlrBaseAgreg>' . $this->fmtVal($vBaseAgreg) . '</vlrBaseAgreg>'
                                          . '<vlrAgreg>' . $this->fmtVal($vAgreg) . '</vlrAgreg>';
                        }
                        if ($vCSLL > 0) {
                            $infoPgtoXml .= '<vlrBaseCSLL>' . $this->fmtVal($vBaseCSLL) . '</vlrBaseCSLL>'
                                          . '<vlrCSLL>' . $this->fmtVal($vCSLL) . '</vlrCSLL>';
                        }
                        if ($vCofins > 0) {
                            $infoPgtoXml .= '<vlrBaseCofins>' . $this->fmtVal($vBaseCofins) . '</vlrBaseCofins>'
                                          . '<vlrCofins>' . $this->fmtVal($vCofins) . '</vlrCofins>';
                        }
                        if ($vPIS > 0) {
                            $infoPgtoXml .= '<vlrBasePP>' . $this->fmtVal($vBasePIS) . '</vlrBasePP>'
                                          . '<vlrPP>' . $this->fmtVal($vPIS) . '</vlrPP>';
                        }
                        $infoPgtoXml .= '</retencoes>';
                    }

                    // Rendimento decorrente de decisão judicial
                    $nrProc = preg_replace('/[^A-Za-z0-9]/', '', (string) ($p['numero_processo'] ?? ''));
                    if ($indJud === 'S' && $nrProc !== '') {
                        $indOrig = (string) ($p['indicador_origem_recurso'] ?? '1');
                        if (!in_array($indOrig, ['1', '2'], true)) {
                            $indOrig = '1';
                        }
                        $infoPgtoXml .= '<infoProcJud>'
                                      . '<nrProc>' . htmlspecialchars($nrProc) . '</nrProc>'
                                      . "<indOrigRec>{$indOrig}</indOrigRec>";
                        if ($indOrig === '2') {
                            $cnpjOrig = preg_replace('/\D/', '', (string) ($p['cnpj_origem_recurso'] ?? ''));
                            if ($cnpjOrig !== '') {
                                $infoPgtoXml .= '<cnpjOrigRecurso>' . $cnpjOrig . '</cnpjOrigRecurso>';
                            }
                        }
                        $infoPgtoXml .= '</infoProcJud>';
                    }

                    $infoPgtoXml .= '</infoPgto>';
                }

                $idePgtoXml .= "<idePgto><natRend>{$natRend}</natRend>{$infoPgtoXml}</idePgto>";
            }

            $benefXml = '<ideBenef>'
                      . "<cnpjBenef>{$cnpjBenef}</cnpjBenef>"
                      . '<nmBenef>' . htmlspecialchars($nome) . '</nmBenef>';
            if ($ideAdic !== '') {
                $benefXml .= '<ideEvtAdic>' . htmlspecialchars(mb_substr($ideAdic, 0, 8)) . '</ideEvtAdic>';
            }
            $benefXml .= $idePgtoXml . '</ideBenef>';

            $cnpjEstab = preg_replace('/\D/', '', $pagtosBenef[0]['cnpj_contribuinte'] ?? $cnpj) ?: $cnpj;

            $body = "        {$this->ideEvento($comp['periodo'], $this->indRetif, $reciboEvt)}\n"
                  . "        {$this->ideContri($cnpj)}\n"
                  . "        <ideEstab><tpInscEstab>1</tpInscEstab><nrInscEstab>{$cnpjEstab}</nrInscEstab>{$benefXml}</ideEstab>";

            $xmls[] = $this->envelope('evtRetPJ', 'evt4020PagtoBeneficiarioPJ', $id, $body);
        }

        return $xmls;
    }

    /** Último recibo consultado de um evento na competência (retificação). */
    private function ultimoReciboEvento(int $competenciaId, string $evento): ?string
    {
        return $this->arquivos->ultimoReciboEvento($competenciaId, $evento);
    }

    /** Mapa cpfBenef => recibo dos XMLs R-4010 já consultados. */
    private function mapRecibosR4010(int $competenciaId): array
    {
        $map = [];
        foreach ($this->arquivos->listXmlsComRecibo($competenciaId, 'R4010') as $row) {
            $xml = (string) ($row['xml_conteudo'] ?? '');
            if (preg_match_all('/<cpfBenef>([^<]+)<\/cpfBenef>/', $xml, $m)) {
                foreach ($m[1] as $cpf) {
                    $cpf = preg_replace('/\D/', '', $cpf) ?? '';
                    if ($cpf !== '' && !isset($map[$cpf])) {
                        $map[$cpf] = (string) $row['nr_recibo_retornado'];
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Mapa chave (cnpjBenef|ideEvtAdic) => nr_recibo_retornado dos XMLs R-4020 já consultados.
     */
    private function mapRecibosR4020(int $competenciaId): array
    {
        $map = [];
        foreach ($this->arquivos->listXmlsComRecibo($competenciaId, 'R4020') as $row) {
            $xml = (string) ($row['xml_conteudo'] ?? '');
            if ($xml === '' || !preg_match('/<cnpjBenef>(\d+)<\/cnpjBenef>/', $xml, $mBenef)) {
                continue;
            }
            $ideAdic = '';
            if (preg_match('/<ideEvtAdic>([^<]*)<\/ideEvtAdic>/', $xml, $mAdic)) {
                $ideAdic = trim($mAdic[1]);
            }
            $chave = $mBenef[1] . '|' . $ideAdic;
            if (!isset($map[$chave])) {
                $map[$chave] = $row['nr_recibo_retornado'];
            }
        }
        return $map;
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

    /**
     * Gera um R-9000 por evento a excluir (recibo + tipo).
     *
     * @param list<array{tp_evento: string, nr_recibo: string, arquivo_id?: int}> $exclusoes
     * @return list<array{evento: string, nome: string, caminho: string, tamanho: int, hash: string, xml: string, nr_recibo_original: string, exclusao_arquivo_id: ?int}>
     */
    public function gerarR9000Exclusoes(array $comp, array $exclusoes): array
    {
        $arquivos = [];
        foreach ($exclusoes as $i => $exc) {
            $tpEvt = $this->formatarTpEvento((string) ($exc['tp_evento'] ?? ''));
            $nrRec = preg_replace('/[^A-Za-z0-9.\\-\/]/', '', (string) ($exc['nr_recibo'] ?? '')) ?? '';
            $nrRec = substr($nrRec, 0, 52);
            if ($tpEvt === '' || $nrRec === '') {
                throw new \RuntimeException('R-9000 exige tipo do evento e número do recibo.');
            }

            $xml = $this->montarXmlR9000($comp, $tpEvt, $nrRec);
            $arq = $this->salvarXml('R9000', $comp, $xml, 1, $i);
            $arq['nr_recibo_original'] = $nrRec;
            $arq['exclusao_arquivo_id'] = isset($exc['arquivo_id']) ? (int) $exc['arquivo_id'] : null;
            $arquivos[] = $arq;
        }
        return $arquivos;
    }

    private function montarXmlR9000(array $comp, string $tpEvento, string $nrRecEvt): string
    {
        $cnpj = preg_replace('/\D/', '', $comp['cnpj']);
        $id   = $this->gerarId($cnpj);
        $per  = htmlspecialchars((string) $comp['periodo'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $tp   = htmlspecialchars($tpEvento, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rec  = htmlspecialchars($nrRecEvt, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $body = "        <ideEvento>\n"
              . "            <tpAmb>{$this->tpAmb}</tpAmb>\n"
              . "            <procEmi>{$this->procEmi}</procEmi>\n"
              . "            <verProc>{$this->verProc}</verProc>\n"
              . "        </ideEvento>\n"
              . "        {$this->ideContri($cnpj)}\n"
              . "        <infoExclusao>"
              . "<tpEvento>{$tp}</tpEvento>"
              . "<nrRecEvt>{$rec}</nrRecEvt>"
              . "<perApur>{$per}</perApur>"
              . "</infoExclusao>";

        return $this->envelope('evtExclusao', 'evtExclusao', $id, $body);
    }

    /** Converte R2010 / R-2010 → R-2010 (tabela 10). */
    public function formatarTpEvento(string $evento): string
    {
        $evento = strtoupper(trim($evento));
        if (preg_match('/^R-?(\d{4})$/', $evento, $m)) {
            return 'R-' . $m[1];
        }
        return '';
    }

    private function gerarR9000(array $comp): string
    {
        $nrRecibo = (string) ($comp['num_recibo'] ?? $this->nrRecibo ?? '');
        $tpEvt    = $this->formatarTpEvento((string) ($comp['tipo_evento_exclusao'] ?? 'R-2010'));
        if ($nrRecibo === '' || $tpEvt === '') {
            throw new \RuntimeException('R-9000 exige tipo do evento e número do recibo (use a exclusão na tela de Transmissão).');
        }
        return $this->montarXmlR9000($comp, $tpEvt, $nrRecibo);
    }
}
