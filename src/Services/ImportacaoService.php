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

        if (count($rows) < 2) {
            throw new \RuntimeException('Planilha vazia ou sem dados após o cabeçalho.');
        }

        // Remover cabeçalho
        array_shift($rows);

        $total      = count($rows);
        $importados = 0;
        $erros      = [];

        foreach ($rows as $rowNum => $row) {
            // Pular linhas completamente vazias
            if (empty(array_filter($row))) continue;

            try {
                match ($evento) {
                    'R2010' => $this->importarR2010($row, $competenciaId),
                    'R2020' => $this->importarR2020($row, $competenciaId),
                    'R2050' => $this->importarR2050($row, $competenciaId),
                    'R2055' => $this->importarR2055($row, $competenciaId),
                    'R2060' => $this->importarR2060($row, $competenciaId),
                    default => throw new \RuntimeException("Evento {$evento} não suportado para importação."),
                };
                $importados++;
            } catch (\Exception $e) {
                $erros[] = "Linha {$rowNum}: " . $e->getMessage();
            }
        }

        if (!empty($erros) && $importados === 0) {
            throw new \RuntimeException(implode("\n", array_slice($erros, 0, 5)));
        }

        return ['total' => $total, 'importados' => $importados, 'erros' => $erros];
    }

    /**
     * Colunas esperadas R-2010:
     * A=CNPJ Prestador, B=Razão Social, C=Nº Documento, D=Data Emissão,
     * E=Valor Bruto, F=Valor Retenção, G=Valor SENAR
     */
    private function importarR2010(array $row, int $competenciaId): void
    {
        $cnpj    = preg_replace('/\D/', '', (string)($row['A'] ?? ''));
        $razao   = trim((string)($row['B'] ?? ''));
        $numDoc  = trim((string)($row['C'] ?? ''));
        $dtEmis  = $this->parseData($row['D'] ?? null);
        $bruto   = $this->parseMoeda($row['E'] ?? 0);
        $retenc  = $this->parseMoeda($row['F'] ?? 0);
        $senar   = $this->parseMoeda($row['G'] ?? 0);

        if (empty($cnpj)) throw new \RuntimeException("CNPJ Prestador vazio.");
        if ($bruto <= 0)  throw new \RuntimeException("Valor Bruto inválido.");

        $tipo = strlen($cnpj) === 14 ? '1' : '2';

        $stmt = $this->db->prepare("
            INSERT INTO r2010 (competencia_id, cnpj_prestador, razao_social_prestador, tipo_insc_prestador,
            num_documento, data_emissao, valor_bruto, valor_retencao, valor_desc_senar)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$competenciaId, $cnpj, $razao, $tipo, $numDoc, $dtEmis, $bruto, $retenc, $senar]);
    }

    /**
     * Colunas esperadas R-2020:
     * A=CNPJ Tomador, B=Razão Social, C=Nº Documento, D=Data Emissão,
     * E=Valor Bruto, F=Valor Retenção
     */
    private function importarR2020(array $row, int $competenciaId): void
    {
        $cnpj   = preg_replace('/\D/', '', (string)($row['A'] ?? ''));
        $razao  = trim((string)($row['B'] ?? ''));
        $numDoc = trim((string)($row['C'] ?? ''));
        $dtEmis = $this->parseData($row['D'] ?? null);
        $bruto  = $this->parseMoeda($row['E'] ?? 0);
        $retenc = $this->parseMoeda($row['F'] ?? 0);

        if (empty($cnpj)) throw new \RuntimeException("CNPJ Tomador vazio.");

        $tipo = strlen($cnpj) === 14 ? '1' : '2';

        $stmt = $this->db->prepare("
            INSERT INTO r2020 (competencia_id, cnpj_tomador, razao_social_tomador, tipo_insc_tomador,
            num_documento, data_emissao, valor_bruto, valor_retencao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$competenciaId, $cnpj, $razao, $tipo, $numDoc, $dtEmis, $bruto, $retenc]);
    }

    /**
     * Colunas esperadas R-2050:
     * A=CNPJ Adquirente, B=Nome, C=Valor Comercialização, D=Contribuição, E=SENAR, F=Data
     */
    private function importarR2050(array $row, int $competenciaId): void
    {
        $cnpj   = preg_replace('/\D/', '', (string)($row['A'] ?? ''));
        $nome   = trim((string)($row['B'] ?? ''));
        $valor  = $this->parseMoeda($row['C'] ?? 0);
        $contrib= $this->parseMoeda($row['D'] ?? 0);
        $senar  = $this->parseMoeda($row['E'] ?? 0);
        $data   = $this->parseData($row['F'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO r2050 (competencia_id, cnpj_adquirente, razao_social,
            valor_comercializacao, valor_contribuicao_previdenciaria, valor_senar, data_operacao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$competenciaId, $cnpj, $nome, $valor, $contrib, $senar, $data]);
    }

    /**
     * Colunas esperadas R-2055:
     * A=CPF Produtor, B=Nome, C=Valor Aquisição, D=Retenção, E=SENAR, F=Data
     */
    private function importarR2055(array $row, int $competenciaId): void
    {
        $cpf   = preg_replace('/\D/', '', (string)($row['A'] ?? ''));
        $nome  = trim((string)($row['B'] ?? ''));
        $valor = $this->parseMoeda($row['C'] ?? 0);
        $ret   = $this->parseMoeda($row['D'] ?? 0);
        $senar = $this->parseMoeda($row['E'] ?? 0);
        $data  = $this->parseData($row['F'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO r2055 (competencia_id, cpf_produtor, nome_produtor,
            valor_aquisicao, valor_retencao, valor_senar, data_aquisicao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$competenciaId, $cpf, $nome, $valor, $ret, $senar, $data]);
    }

    /**
     * Colunas esperadas R-2060:
     * A=CNAE, B=Rec Bruta, C=Exclusões, D=Alíquota
     */
    private function importarR2060(array $row, int $competenciaId): void
    {
        $cnae   = trim((string)($row['A'] ?? ''));
        $bruta  = $this->parseMoeda($row['B'] ?? 0);
        $excl   = $this->parseMoeda($row['C'] ?? 0);
        $aliq   = (float) str_replace(',', '.', (string)($row['D'] ?? 0));
        $base   = $bruta - $excl;
        $contrib= round($base * ($aliq / 100), 2);

        $stmt = $this->db->prepare("
            INSERT INTO r2060 (competencia_id, cnae, valor_rec_bruta, valor_rec_bruta_excl,
            valor_base_calculo, aliquota, valor_contribuicao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$competenciaId, $cnae, $bruta, $excl, $base, $aliq, $contrib]);
    }

    private function parseMoeda(mixed $val): float
    {
        $v = str_replace(['R$', ' ', '.'], ['', '', ''], (string) $val);
        $v = str_replace(',', '.', $v);
        return (float) $v;
    }

    private function parseData(mixed $val): ?string
    {
        if (empty($val)) return null;

        // Número serial do Excel
        if (is_numeric($val)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val);
            return $date->format('Y-m-d');
        }

        // Formatos comuns BR
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim((string)$val));
            if ($d) return $d->format('Y-m-d');
        }
        return null;
    }
}
