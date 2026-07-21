<?php

declare(strict_types=1);

/**
 * Gera 4 planilhas (R-2010/2055/4010/4020) para testar o fluxo.
 * Competência 10/2025 — dados distintos de teste_fluxo_2025-09.
 *
 * Uso: php scripts/gerar_planilhas_teste_fluxo_2025_10.php [diretorio_saida]
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$outDir = $argv[1] ?? (getenv('HOME') . '/Downloads/teste_fluxo_2025-10');
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

/** Contribuinte de homologação (Juazeiro do Norte). */
$cnpj = '07974082000114';

$headerStyle = [
    'font' => ['bold' => true, 'size' => 10],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9E2F3'],
    ],
    'alignment' => [
        'wrapText' => true,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$writeFile = static function (
    string $path,
    string $title,
    array $headers,
    array $rows
) use ($headerStyle): void {
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle($title);

    $col = 1;
    foreach ($headers as $h) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $h);
        $col++;
    }
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(36);

    foreach ($rows as $rIdx => $row) {
        $line = $rIdx + 2;
        $c = 1;
        foreach ($row as $val) {
            $coord = Coordinate::stringFromColumnIndex($c) . $line;
            $sheet->setCellValueExplicit($coord, (string) $val, DataType::TYPE_STRING);
            $c++;
        }
    }

    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimensionByColumn($i)->setWidth(16);
    }

    $writer = new Xlsx($ss);
    $writer->save($path);
    echo "OK: {$path}\n";
};

// ─── R-2010: NFS/valores/datas novos (out/2025) ───
$writeFile(
    $outDir . '/teste_R2010_2025-10.xlsx',
    'R2010 - Serviços tomados',
    [
        '* CNPJ EMPRESA',
        'CNO',
        'OBRA',
        '* CNPJ DO PRESTADOR',
        'CPRB (0/1)',
        'SÉRIE',
        '* NFS',
        '* DATA_EMISSAO (DD/MM/AAAA)',
        '* VALOR BRUTO',
        'OBS',
        '* COD TIPO SERVIÇO TAB.6',
        'BASE CÁLCULO',
        'RETENÇÃO',
    ],
    [
        [$cnpj, '', '', '07195191000133', '0', '1', '10201', '08/10/2025', '9800.00', 'Suporte sistemas', '100000001', '9800.00', '1078.00'],
        [$cnpj, '', '', '41771364000152', '0', '1', '10202', '14/10/2025', '15450.25', 'Manutencao predial', '100000003', '15450.25', '1699.53'],
        [$cnpj, '', '', '27843749000157', '0', '0', '10203', '27/10/2025', '6200.00', 'Treinamento', '100000001', '6200.00', '682.00'],
    ]
);

// ─── R-2055: produtores e valores novos ───
$writeFile(
    $outDir . '/teste_R2055_2025-10.xlsx',
    'R2055 - Aquisição Prod Rural',
    [
        '* CNPJ EMPRESA',
        '* ADQUIRENTE (CNPJ/CAEPF)',
        '* PRODUTOR (CNPJ/CPF)',
        '* PERÍODO (MM/AAAA ou data)',
        'indOpcCP (S ou vazio)',
        'indAquis',
        '* VALOR BRUTO',
        'CP DESCONTADA',
        'RAT/GILRAT',
        'SENAR',
    ],
    [
        [$cnpj, $cnpj, '52998224725', '10/2025', '', '1', '11200.00', '168.00', '22.40', '28.00'],
        [$cnpj, $cnpj, '20612147000140', '10/2025', '', '3', '22340.50', '335.11', '44.68', '55.85'],
    ]
);

// ─── R-4010: CPFs/valores/naturezas distintos ───
$writeFile(
    $outDir . '/teste_R4010_2025-10.xlsx',
    'R4010 - Beneficiário PF',
    [
        '* CNPJ CONTRIBUINTE',
        '* CPF DO BENEFICIÁRIO',
        'NOME DO BENEFICIÁRIO',
        '* PERÍODO APURAÇÃO (01/MM/AAAA)',
        '* DATA DO FATO GERADOR (DD/MM/AAAA)',
        '* VALOR BRUTO',
        '* NATUREZA RENDIMENTO TAB.01',
        'DEDUÇÃO / OUTROS',
        '* VALOR_BC_CALCULO',
        'VL_IRRF',
        '',
        '',
        '',
        '',
        'OBSERVAÇÕES',
    ],
    [
        [$cnpj, '52998224725', 'CARLOS MENDES OLIVEIRA', '01/10/2025', '10/10/2025', '5100.00', '12002', '0', '5100.00', '765.00', '', '', '', '', 'Honorarios out/25'],
        [$cnpj, '11144477735', 'ANA PAULA FERREIRA', '01/10/2025', '16/10/2025', '3300.00', '13002', '0', '3300.00', '495.00', '', '', '', '', 'Locacao PF out'],
        [$cnpj, '39053344705', 'PEDRO HENRIQUE LIMA', '01/10/2025', '28/10/2025', '2450.75', '12003', '0', '2450.75', '367.61', '', '', '', '', 'Comissoes out'],
    ]
);

// ─── R-4020: NFS/naturezas/valores novos ───
$writeFile(
    $outDir . '/teste_R4020_2025-10.xlsx',
    'R4020 - Beneficiário PJ',
    [
        '* CNPJ CONTRIBUINTE',
        '* CNPJ DO PRESTADOR',
        '* NFS',
        '* PERÍODO APURAÇÃO (01/MM/AAAA)',
        '* DATA DO FATO GERADOR (DD/MM/AAAA)',
        '* VALOR BRUTO',
        '* COD TIPO_SERVIÇO TAB 4020',
        'CÓDIGO DO PAÍS',
        '* VALOR_BC_CALCULO',
        'VL_IRRF',
        'CSRF (valor agregado)',
        'VL_CSLL (5979)',
        'VL_PIS (5960)',
        'VL_COFINS (5987)',
        'IDENTIFICADOR',
        'INDICADOR FCI/SCP',
        'CNPJ FCI/SCP',
        'PERCENTUAL SCP',
        'IND JUDICIAL',
        'NR_PROCESSO',
        'IND_ORIGEM_RECURSO',
        'OBSERVAÇÕES',
    ],
    [
        [$cnpj, '07195191000133', '10251', '01/10/2025', '09/10/2025', '15200.00', '17001', '', '15200.00', '228.00', '0', '152.00', '98.80', '456.00', '', '', '', '', '', '', '', 'PJ alimentacao out'],
        // 17013: IR/CSLL/Agreg — CSRF na col. K (sem COFINS/PP separados)
        [$cnpj, '41771364000152', '10252', '01/10/2025', '17/10/2025', '8750.00', '17013', '', '8750.00', '131.25', '218.75', '87.50', '0', '0', 'LOTE-OUT', '', '', '', '', '', '', 'Combustivel agreg out'],
        [$cnpj, '27843749000157', '10253', '01/10/2025', '29/10/2025', '4100.00', '15010', '', '4100.00', '0', '0', '0', '0', '0', '', '', '', '', '', '', '', 'Auditoria sem retencao out'],
    ]
);

echo "\nPasta: {$outDir}\n";
echo "Contribuinte: {$cnpj} | Período: 10/2025\n";
echo "Importar no app com modo por data/período e contribuinte 07974082000114.\n";
