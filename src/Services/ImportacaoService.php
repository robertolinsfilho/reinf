<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportacaoService
{
    public function __construct(private \PDO $db) {}

    public function processar(string $arquivo, string $evento, int $competenciaId): array
    {
        $spreadsheet = IOFactory::load($arquivo);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        // Remover cabeçalho
        $header = array_shift($rows);

        $importados = 0;
        $erros      = [];

        foreach ($rows as $i => $row) {
            $linha = $i + 2; // +2 porque removemos header e arrays começam em 1
            try {
                // Pular linhas vazias
                $valores = array_filter($row, fn($v) => $v !== null && $v !== '');
                if (empty($valores)) continue;

                match ($evento) {
                    'R2010' => $this->importarR2010($row, $competenciaId),
                    'R2020' => $this->importarR2020($row, $competenciaId),
                    'R2060' => $this->importarR2060($row, $competenciaId),
                    'R4010' => $this->importarR4010($row, $competenciaId),
                    'R4020' => $this->importarR4020($row, $competenciaId),
                    default => throw new \RuntimeException("Evento {$evento} não suportado para importação."),
                };
                $importados++;
            } catch (\Throwable $e) {
                $erros[] = "Linha {$linha}: " . $e->getMessage();
            }
        }

        return [
            'total'      => count($rows),
            'importados' => $importados,
            'erros'      => $erros,
        ];
    }

    // ─── R-2010 ──────────────────────────────────────────────

    private function importarR2010(array $row, int $competenciaId): void
    {
        // Colunas esperadas: A=CNPJ, B=Razão Social, C=Tipo Insc, D=Nº Doc, E=Data, F=Bruto, G=Retenção, H=SENAR
        $stmt = $this->db->prepare("
            INSERT INTO r2010 (competencia_id, cnpj_prestador, razao_social_prestador, tipo_insc_prestador,
            num_documento, data_emissao, valor_bruto, valor_retencao, valor_desc_senar)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            preg_replace('/\D/', '', $row['A'] ?? ''),
            $row['B'] ?? '',
            $row['C'] ?? '1',
            $row['D'] ?? '',
            $this->parseData($row['E'] ?? null),
            $this->parseMoeda($row['F'] ?? 0),
            $this->parseMoeda($row['G'] ?? 0),
            $this->parseMoeda($row['H'] ?? 0),
        ]);
    }

    // ─── R-2020 ──────────────────────────────────────────────

    private function importarR2020(array $row, int $competenciaId): void
    {
        // Colunas: A=CNPJ Tomador, B=Razão Social, C=Tipo Insc, D=Nº Doc, E=Data, F=Bruto, G=Retenção
        $stmt = $this->db->prepare("
            INSERT INTO r2020 (competencia_id, cnpj_tomador, razao_social_tomador, tipo_insc_tomador,
            num_documento, data_emissao, valor_bruto, valor_retencao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            preg_replace('/\D/', '', $row['A'] ?? ''),
            $row['B'] ?? '',
            $row['C'] ?? '1',
            $row['D'] ?? '',
            $this->parseData($row['E'] ?? null),
            $this->parseMoeda($row['F'] ?? 0),
            $this->parseMoeda($row['G'] ?? 0),
        ]);
    }

    // ─── R-2060 ──────────────────────────────────────────────

    private function importarR2060(array $row, int $competenciaId): void
    {
        // Colunas: A=CNAE, B=Rec Bruta, C=Exclusões, D=Alíquota
        $recBruta   = $this->parseMoeda($row['B'] ?? 0);
        $exclusoes  = $this->parseMoeda($row['C'] ?? 0);
        $aliquota   = $this->parseMoeda($row['D'] ?? 0);
        $base       = $recBruta - $exclusoes;
        $cprb       = round($base * ($aliquota / 100), 2);

        $stmt = $this->db->prepare("
            INSERT INTO r2060 (competencia_id, cnae, valor_rec_bruta, valor_exclusoes,
            valor_base_calculo, aliquota, valor_cprb)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            $row['A'] ?? '',
            $recBruta,
            $exclusoes,
            $base,
            $aliquota,
            $cprb,
        ]);
    }

    // ─── R-4010 ──────────────────────────────────────────────

    private function importarR4010(array $row, int $competenciaId): void
    {
        // Colunas: A=CPF, B=Nome, C=Natureza, D=Data Pagto, E=Bruto, F=Base IR, G=IR, H=Dedução
        $stmt = $this->db->prepare("
            INSERT INTO r4010 (competencia_id, cpf_beneficiario, nome_beneficiario, natureza_rendimento,
            data_pagamento, valor_bruto, valor_base_ir, valor_ir, valor_deducao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            preg_replace('/\D/', '', $row['A'] ?? ''),
            $row['B'] ?? '',
            $row['C'] ?? '',
            $this->parseData($row['D'] ?? null),
            $this->parseMoeda($row['E'] ?? 0),
            $this->parseMoeda($row['F'] ?? 0),
            $this->parseMoeda($row['G'] ?? 0),
            $this->parseMoeda($row['H'] ?? 0),
        ]);
    }

    // ─── R-4020 ──────────────────────────────────────────────

   private function importarR4020(array $row, int $competenciaId): void
    {
        // Formato oficial (planilha RFB - 22 colunas):
        // A=CNPJ Contribuinte, B=CNPJ Prestador, C=Nº NFS, D=Período Apuração,
        // E=Data Fato Gerador, F=Valor Bruto, G=Cod Tipo Serviço, H=Cód País,
        // I=Base Cálculo, J=IRRF, K=CSRF agregado, L=CSLL, M=PIS, N=COFINS,
        // O=Identificador, P=Ind FCI/SCP, Q=CNPJ FCI/SCP, R=% SCP,
        // S=Ind Judicial, T=Nº Processo, U=Ind Origem, V=Observações

        $cnpjBenef = preg_replace('/\D/', '', (string)($row['B'] ?? ''));

        // Pular linhas em branco
        if (empty($cnpjBenef) || strlen($cnpjBenef) < 11) {
            return;
        }

        $codTipoServico = str_pad(trim((string)($row['G'] ?? '')), 5, '0', STR_PAD_LEFT);
        $natRend        = $codTipoServico; // No R-4020, natureza = cod tipo serviço da Tab 4020

        $stmt = $this->db->prepare("
            INSERT INTO r4020 (
                competencia_id, cnpj_contribuinte, cnpj_beneficiario, num_nfs,
                periodo_apuracao, natureza_rendimento, cod_tipo_servico, cod_pais,
                data_pagamento, valor_bruto, valor_base_ir, valor_ir,
                vl_csrf_agregado, valor_csll, valor_pis, valor_cofins,
                identificador_adicional, indicador_fci_scp, cnpj_fci_scp, percentual_scp,
                indicador_judicial, numero_processo, indicador_origem_recurso, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $competenciaId,
            preg_replace('/\D/', '', (string)($row['A'] ?? '')) ?: null,
            $cnpjBenef,
            (string)($row['C'] ?? ''),
            $this->parseData($row['D'] ?? null),
            $natRend,
            $codTipoServico,
            (string)($row['H'] ?? '') ?: null,
            $this->parseData($row['E'] ?? null),
            $this->parseMoeda($row['F'] ?? 0),
            $this->parseMoeda($row['I'] ?? 0),
            $this->parseMoeda($row['J'] ?? 0),
            $this->parseMoeda($row['K'] ?? 0),
            $this->parseMoeda($row['L'] ?? 0),
            $this->parseMoeda($row['M'] ?? 0),
            $this->parseMoeda($row['N'] ?? 0),
            (string)($row['O'] ?? '') ?: null,
            !empty($row['P']) ? (int)$row['P'] : null,
            preg_replace('/\D/', '', (string)($row['Q'] ?? '')) ?: null,
            !empty($row['R']) ? (float)$row['R'] : null,
            !empty($row['S']) ? 1 : 0,
            (string)($row['T'] ?? '') ?: null,
            !empty($row['U']) ? (int)$row['U'] : null,
            (string)($row['V'] ?? '') ?: null,
        ]);
    }
    private function parseMoeda(mixed $val): float
    {
        if (is_numeric($val)) return (float) $val;
        $val = str_replace(['.', ','], ['', '.'], (string) $val);
        return (float) $val;
    }

    private function parseData(mixed $val): ?string
    {
        if (!$val) return null;

        // Se é um objeto DateTime (do PhpSpreadsheet quando lê datas do Excel)
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d');
        }

        // Se é serial number do Excel
        if (is_numeric($val)) {
            $unix = ((int) $val - 25569) * 86400;
            return date('Y-m-d', $unix);
        }

        // String
        $ts = strtotime((string) $val);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}