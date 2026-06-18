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
        // Colunas: A=CNPJ, B=Razão Social, C=Natureza, D=Data Pagto, E=Bruto, F=Base IR, G=IR, H=CSLL, I=COFINS, J=PIS
        $stmt = $this->db->prepare("
            INSERT INTO r4020 (competencia_id, cnpj_beneficiario, razao_social_beneficiario, natureza_rendimento,
            data_pagamento, valor_bruto, valor_base_ir, valor_ir, valor_csll, valor_cofins, valor_pis)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $this->parseMoeda($row['I'] ?? 0),
            $this->parseMoeda($row['J'] ?? 0),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function parseMoeda(mixed $val): float
    {
        if (is_numeric($val)) return (float) $val;
        $val = str_replace(['.', ','], ['', '.'], (string) $val);
        return (float) $val;
    }

    private function parseData(mixed $val): ?string
    {
        if (!$val) return null;
        if (is_numeric($val)) {
            // Excel serial date
            $unix = ($val - 25569) * 86400;
            return date('Y-m-d', (int) $unix);
        }
        $ts = strtotime((string) $val);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}