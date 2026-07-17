<?php

namespace App\Services;

use App\Repositories\CompetenciaRepository;
use App\Repositories\ContribuinteRepository;
use App\Repositories\EventoRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportacaoService
{
    private EventoRepository $eventos;
    private ContribuinteRepository $contribuintes;
    private CompetenciaRepository $competencias;

    public function __construct(\PDO $db)
    {
        $this->eventos       = new EventoRepository($db);
        $this->contribuintes = new ContribuinteRepository($db);
        $this->competencias  = new CompetenciaRepository($db);
    }

    public function processar(string $arquivo, string $evento, int $competenciaId, int $maxRows = 0): array
    {
        if ($evento === 'R2010') {
            [$rows, $layout] = $this->carregarLinhasR2010($arquivo);
        } elseif ($evento === 'R2055') {
            [$rows] = $this->carregarLinhasR2055($arquivo);
            $layout = 'oficial';
        } else {
            $spreadsheet = IOFactory::load($arquivo);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);
            array_shift($rows);
            $layout = 'simples';
        }

        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new \RuntimeException("Planilha excede o limite de {$maxRows} linhas por importação.");
        }

        $importados = 0;
        $erros      = [];

        foreach ($rows as $i => $row) {
            $linha = $i + 2;
            try {
                $valores = array_filter($row, fn($v) => $v !== null && $v !== '');
                if (empty($valores)) {
                    continue;
                }

                $ok = match ($evento) {
                    'R2010' => $this->importarR2010($row, $competenciaId, $layout),
                    'R2020' => $this->importarR2020($row, $competenciaId),
                    'R2055' => $this->importarR2055($row, $competenciaId),
                    'R2060' => $this->importarR2060($row, $competenciaId),
                    'R4010' => $this->importarR4010($row, $competenciaId),
                    'R4020' => $this->importarR4020($row, $competenciaId),
                    default => throw new \RuntimeException("Evento {$evento} não suportado para importação."),
                };
                if ($ok) {
                    $importados++;
                }
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

    /**
     * Importação automática: cria competências pelo período das datas da planilha.
     * Eventos suportados: R2010, R2055, R4020.
     */
    public function processarPorPeriodo(
        string $arquivo,
        string $evento,
        int $userId,
        ?int $contribuinteId = null,
        int $maxRows = 0
    ): array {
        return match ($evento) {
            'R2010' => $this->processarR2010PorPeriodo($arquivo, $userId, $contribuinteId, $maxRows),
            'R2055' => $this->processarR2055PorPeriodo($arquivo, $userId, $contribuinteId, $maxRows),
            'R4020' => $this->processarR4020PorPeriodo($arquivo, $userId, $contribuinteId, $maxRows),
            default => throw new \RuntimeException(
                "Criação automática de competências ainda não disponível para {$evento}."
            ),
        };
    }

    /**
     * R-2010: período pela data de emissão.
     * Aceita layout simples (col. E) ou modelo oficial (col. H / CNPJ empresa na A).
     */
    public function processarR2010PorPeriodo(
        string $arquivo,
        int $userId,
        ?int $contribuinteId = null,
        int $maxRows = 0
    ): array {
        $fallback = null;
        if ($contribuinteId) {
            $fallback = $this->contribuintes->findByUser($contribuinteId, $userId);
            if (!$fallback) {
                throw new \RuntimeException('Contribuinte selecionado não encontrado.');
            }
        }

        [$rows, $layout] = $this->carregarLinhasR2010($arquivo);
        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new \RuntimeException("Planilha excede o limite de {$maxRows} linhas por importação.");
        }

        $cacheComp = [];
        $resumo    = [];
        $importados = 0;
        $erros      = [];
        $totalLinhasUteis = 0;

        foreach ($rows as $i => $row) {
            $linha = $i + 2;
            try {
                $valores = array_filter($row, fn($v) => $v !== null && $v !== '');
                if (empty($valores)) {
                    continue;
                }

                $norm = $this->normalizarLinhaR2010($row, $layout);
                if ($norm['cnpj_prestador'] === '' || strlen($norm['cnpj_prestador']) < 11) {
                    continue;
                }
                $totalLinhasUteis++;

                if (!$norm['data_emissao']) {
                    $colData = $layout === 'oficial' ? 'H' : 'E';
                    throw new \RuntimeException("Sem data de emissão (coluna {$colData}).");
                }
                $periodo = substr($norm['data_emissao'], 0, 7);

                $contribuinte = null;
                if ($norm['cnpj_empresa'] !== '') {
                    $contribuinte = $this->contribuintes->findByCnpjAndUser($norm['cnpj_empresa'], $userId);
                }
                if (!$contribuinte) {
                    $contribuinte = $fallback;
                }
                if (!$contribuinte) {
                    throw new \RuntimeException(
                        $norm['cnpj_empresa'] !== ''
                            ? "CNPJ contribuinte {$norm['cnpj_empresa']} não cadastrado. Cadastre-o ou selecione um contribuinte padrão."
                            : 'Selecione o contribuinte (planilha sem CNPJ da empresa).'
                    );
                }

                $chave = $contribuinte['id'] . '|' . $periodo;
                if (!isset($cacheComp[$chave])) {
                    $res = $this->competencias->findOrCreate((int) $contribuinte['id'], $periodo);
                    $cacheComp[$chave] = [
                        'id'     => (int) $res['competencia']['id'],
                        'criada' => (bool) $res['criada'],
                    ];
                }

                $compId = $cacheComp[$chave]['id'];
                if ($this->inserirR2010Normalizado($norm, $compId)) {
                    $importados++;
                    if (!isset($resumo[$chave])) {
                        $resumo[$chave] = [
                            'periodo'    => $periodo,
                            'id'         => $compId,
                            'criada'     => $cacheComp[$chave]['criada'],
                            'importados' => 0,
                        ];
                    }
                    $resumo[$chave]['importados']++;
                }
            } catch (\Throwable $e) {
                $erros[] = "Linha {$linha}: " . $e->getMessage();
            }
        }

        return [
            'total'               => $totalLinhasUteis,
            'importados'          => $importados,
            'erros'               => $erros,
            'competencias'        => array_values($resumo),
            'log_competencia_ids' => array_values(array_unique(array_column(array_values($resumo), 'id'))),
        ];
    }

    /**
     * R-2055: período pela coluna D (MM/AAAA ou data). CNPJ empresa na coluna A.
     */
    public function processarR2055PorPeriodo(
        string $arquivo,
        int $userId,
        ?int $contribuinteId = null,
        int $maxRows = 0
    ): array {
        $fallback = null;
        if ($contribuinteId) {
            $fallback = $this->contribuintes->findByUser($contribuinteId, $userId);
            if (!$fallback) {
                throw new \RuntimeException('Contribuinte selecionado não encontrado.');
            }
        }

        [$rows] = $this->carregarLinhasR2055($arquivo);
        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new \RuntimeException("Planilha excede o limite de {$maxRows} linhas por importação.");
        }

        $cacheComp = [];
        $resumo    = [];
        $importados = 0;
        $erros      = [];
        $totalLinhasUteis = 0;

        foreach ($rows as $i => $row) {
            $linha = $i + 2;
            try {
                $valores = array_filter($row, fn($v) => $v !== null && $v !== '');
                if (empty($valores)) {
                    continue;
                }

                $norm = $this->normalizarLinhaR2055($row);
                if ($norm['nr_insc_produtor'] === '') {
                    continue;
                }
                $totalLinhasUteis++;

                if (!$norm['periodo']) {
                    throw new \RuntimeException('Sem período de apuração (coluna D).');
                }
                $periodo = $norm['periodo'];

                $contribuinte = null;
                if ($norm['cnpj_empresa'] !== '') {
                    $contribuinte = $this->contribuintes->findByCnpjAndUser($norm['cnpj_empresa'], $userId);
                }
                if (!$contribuinte) {
                    $contribuinte = $fallback;
                }
                if (!$contribuinte) {
                    throw new \RuntimeException(
                        $norm['cnpj_empresa'] !== ''
                            ? "CNPJ contribuinte {$norm['cnpj_empresa']} não cadastrado. Cadastre-o ou selecione um contribuinte padrão."
                            : 'Selecione o contribuinte (planilha sem CNPJ da empresa).'
                    );
                }

                $chave = $contribuinte['id'] . '|' . $periodo;
                if (!isset($cacheComp[$chave])) {
                    $res = $this->competencias->findOrCreate((int) $contribuinte['id'], $periodo);
                    $cacheComp[$chave] = [
                        'id'     => (int) $res['competencia']['id'],
                        'criada' => (bool) $res['criada'],
                    ];
                }

                $compId = $cacheComp[$chave]['id'];
                if ($this->inserirR2055Normalizado($norm, $compId)) {
                    $importados++;
                    if (!isset($resumo[$chave])) {
                        $resumo[$chave] = [
                            'periodo'    => $periodo,
                            'id'         => $compId,
                            'criada'     => $cacheComp[$chave]['criada'],
                            'importados' => 0,
                        ];
                    }
                    $resumo[$chave]['importados']++;
                }
            } catch (\Throwable $e) {
                $erros[] = "Linha {$linha}: " . $e->getMessage();
            }
        }

        return [
            'total'               => $totalLinhasUteis,
            'importados'          => $importados,
            'erros'               => $erros,
            'competencias'        => array_values($resumo),
            'log_competencia_ids' => array_values(array_unique(array_column(array_values($resumo), 'id'))),
        ];
    }

    /**
     * R-4020: cria competências automaticamente pelo período (data fato gerador)
     * e importa cada linha na competência correspondente.
     */
    public function processarR4020PorPeriodo(
        string $arquivo,
        int $userId,
        ?int $contribuinteFallbackId = null,
        int $maxRows = 0
    ): array {
        $spreadsheet = IOFactory::load($arquivo);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);
        array_shift($rows);

        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new \RuntimeException("Planilha excede o limite de {$maxRows} linhas por importação.");
        }

        $fallback = null;
        if ($contribuinteFallbackId) {
            $fallback = $this->contribuintes->findByUser($contribuinteFallbackId, $userId);
            if (!$fallback) {
                throw new \RuntimeException('Contribuinte selecionado não encontrado.');
            }
        }

        $cacheComp = [];
        $resumo    = [];
        $importados = 0;
        $erros      = [];
        $totalLinhasUteis = 0;

        foreach ($rows as $i => $row) {
            $linha = $i + 2;
            try {
                $valores = array_filter($row, fn($v) => $v !== null && $v !== '');
                if (empty($valores)) {
                    continue;
                }

                $cnpjBenef = preg_replace('/\D/', '', (string) ($row['B'] ?? ''));
                if ($cnpjBenef === '' || strlen($cnpjBenef) < 11) {
                    continue;
                }
                $totalLinhasUteis++;

                $periodo = $this->extrairPeriodoR4020($row);
                if (!$periodo) {
                    throw new \RuntimeException('Sem data fato gerador / período de apuração.');
                }

                $cnpjContri = preg_replace('/\D/', '', (string) ($row['A'] ?? '')) ?: '';
                $contribuinte = null;
                if ($cnpjContri !== '') {
                    $contribuinte = $this->contribuintes->findByCnpjAndUser($cnpjContri, $userId);
                }
                if (!$contribuinte) {
                    $contribuinte = $fallback;
                }
                if (!$contribuinte) {
                    throw new \RuntimeException(
                        $cnpjContri !== ''
                            ? "CNPJ contribuinte {$cnpjContri} não cadastrado. Cadastre-o ou selecione um contribuinte padrão."
                            : 'CNPJ contribuinte vazio. Selecione um contribuinte padrão.'
                    );
                }

                $chave = $contribuinte['id'] . '|' . $periodo;
                if (!isset($cacheComp[$chave])) {
                    $res = $this->competencias->findOrCreate((int) $contribuinte['id'], $periodo);
                    $cacheComp[$chave] = [
                        'id'      => (int) $res['competencia']['id'],
                        'periodo' => $periodo,
                        'criada'  => (bool) $res['criada'],
                        'cnpj'    => $contribuinte['cnpj'],
                    ];
                }

                $compId = $cacheComp[$chave]['id'];
                if ($this->importarR4020($row, $compId)) {
                    $importados++;
                    if (!isset($resumo[$chave])) {
                        $resumo[$chave] = [
                            'periodo'    => $periodo,
                            'id'         => $compId,
                            'criada'     => $cacheComp[$chave]['criada'],
                            'importados' => 0,
                        ];
                    }
                    $resumo[$chave]['importados']++;
                }
            } catch (\Throwable $e) {
                $erros[] = "Linha {$linha}: " . $e->getMessage();
            }
        }

        return [
            'total'               => $totalLinhasUteis,
            'importados'          => $importados,
            'erros'               => $erros,
            'competencias'        => array_values($resumo),
            'log_competencia_ids' => array_values(array_unique(array_column(array_values($resumo), 'id'))),
        ];
    }

    private function periodoFromData(mixed $val): ?string
    {
        $parsed = $this->parseData($val);
        return $parsed ? substr($parsed, 0, 7) : null;
    }

    /**
     * Período YYYY-MM a partir da data do fato gerador (E) ou período apuração (D).
     */
    private function extrairPeriodoR4020(array $row): ?string
    {
        $periodo = $this->periodoFromData($row['E'] ?? null);
        if ($periodo) {
            return $periodo;
        }

        $d = $row['D'] ?? null;
        if ($d instanceof \DateTimeInterface) {
            return $d->format('Y-m');
        }

        $periodo = $this->periodoFromData($d);
        if ($periodo) {
            return $periodo;
        }

        $s = trim((string) ($d ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})$/', $s, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[2] . '-' . $m[1];
        }

        return null;
    }

    // ─── R-2010 ──────────────────────────────────────────────

    /**
     * @return array{0: list<array>, 1: string} [linhas sem cabeçalho, layout]
     */
    private function carregarLinhasR2010(string $arquivo): array
    {
        $spreadsheet = IOFactory::load($arquivo);
        $sheet       = $this->encontrarAbaR2010($spreadsheet);
        $rows        = $sheet->toArray(null, true, true, true);
        $header      = array_shift($rows) ?? [];
        $layout      = $this->detectarLayoutR2010($header);

        return [$rows, $layout];
    }

    private function encontrarAbaR2010(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $titulo = mb_strtolower($sheet->getTitle());
            $titulo = str_replace(['ç', 'ã', 'á', 'é'], ['c', 'a', 'a', 'e'], $titulo);
            if (str_contains($titulo, 'r2010') || str_contains($titulo, 'servicos tomados')) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    private function detectarLayoutR2010(array $header): string
    {
        $blob = mb_strtoupper(implode(' ', array_map(
            static fn($v) => preg_replace('/\s+/', ' ', (string) $v),
            $header
        )));

        if (
            str_contains($blob, 'DATA_EMISSAO')
            || str_contains($blob, 'DATA EMISSAO')
            || str_contains($blob, 'CNPJ DO PRESTADOR')
            || str_contains($blob, 'CNPJ EMPRESA')
            || str_contains($blob, 'COD TIPO')
        ) {
            return 'oficial';
        }

        return 'simples';
    }

    /**
     * Normaliza linha do Excel para campos internos do R-2010.
     *
     * @return array{
     *   cnpj_empresa: string,
     *   cnpj_prestador: string,
     *   razao_social: string,
     *   tipo_insc: string,
     *   num_documento: string,
     *   serie: string,
     *   data_emissao: ?string,
     *   valor_bruto: float,
     *   valor_base: float,
     *   valor_retencao: float,
     *   valor_senar: float,
     *   cod_servico: string,
     *   ind_cprb: string
     * }
     */
    private function normalizarLinhaR2010(array $row, string $layout): array
    {
        if ($layout === 'oficial') {
            // A=CNPJ empresa, C=obra, D=prestador, E=CPRB, F=série, G=NFS,
            // H=data, I=bruto, K=tipo serviço, L=base, M=retenção
            $cnpjEmpresa = preg_replace('/\D/', '', (string) ($row['A'] ?? ''));
            $cnpjPrest   = preg_replace('/\D/', '', (string) ($row['D'] ?? ''));
            $indCprb     = trim((string) ($row['E'] ?? '0'));
            if (!in_array($indCprb, ['0', '1'], true)) {
                $indCprb = '0';
            }

            $valorBruto = $this->parseMoeda($row['I'] ?? 0);
            $valorBase  = $this->parseMoeda($row['L'] ?? 0);
            $valorRet   = $this->parseMoeda($row['M'] ?? 0);
            if ($valorBase <= 0) {
                $valorBase = $valorBruto;
            }
            if ($valorRet <= 0 && $valorBase > 0) {
                $aliq = $indCprb === '1' ? 0.035 : 0.11;
                $valorRet = round($valorBase * $aliq, 2);
            }

            $serie = trim((string) ($row['F'] ?? ''));
            if ($serie === '') {
                $serie = '0';
            }

            return [
                'cnpj_empresa'   => $cnpjEmpresa,
                'cnpj_prestador' => $cnpjPrest,
                'razao_social'   => '',
                'tipo_insc'      => strlen($cnpjPrest) === 11 ? '2' : '1',
                'num_documento'  => trim((string) ($row['G'] ?? '')),
                'serie'          => $serie,
                'data_emissao'   => $this->parseData($row['H'] ?? null),
                'valor_bruto'    => $valorBruto,
                'valor_base'     => $valorBase,
                'valor_retencao' => $valorRet,
                'valor_senar'    => 0.0,
                'cod_servico'    => $this->extrairCodServicoR2010($row['K'] ?? ''),
                'ind_cprb'       => $indCprb,
            ];
        }

        // Layout simples: A=prestador … E=data … J=tipo serviço
        $cnpjPrest  = preg_replace('/\D/', '', (string) ($row['A'] ?? ''));
        $valorBruto = $this->parseMoeda($row['F'] ?? 0);
        $valorRet   = $this->parseMoeda($row['G'] ?? 0);
        $valorBase  = $this->parseMoeda($row['I'] ?? 0);
        if ($valorBase <= 0) {
            $valorBase = $valorBruto;
        }

        $indCprb = (string) ($row['K'] ?? '0');
        if (!in_array($indCprb, ['0', '1'], true)) {
            $indCprb = '0';
        }
        if ($valorRet <= 0 && $valorBase > 0) {
            $aliq = $indCprb === '1' ? 0.035 : 0.11;
            $valorRet = round($valorBase * $aliq, 2);
        }

        $serie = trim((string) ($row['L'] ?? ''));
        if ($serie === '') {
            $serie = '0';
        }

        return [
            'cnpj_empresa'   => '',
            'cnpj_prestador' => $cnpjPrest,
            'razao_social'   => (string) ($row['B'] ?? ''),
            'tipo_insc'      => (string) ($row['C'] ?? (strlen($cnpjPrest) === 11 ? '2' : '1')),
            'num_documento'  => (string) ($row['D'] ?? ''),
            'serie'          => $serie,
            'data_emissao'   => $this->parseData($row['E'] ?? null),
            'valor_bruto'    => $valorBruto,
            'valor_base'     => $valorBase,
            'valor_retencao' => $valorRet,
            'valor_senar'    => $this->parseMoeda($row['H'] ?? 0),
            'cod_servico'    => $this->extrairCodServicoR2010($row['J'] ?? '100000001'),
            'ind_cprb'       => $indCprb,
        ];
    }

    private function extrairCodServicoR2010(mixed $val): string
    {
        if (preg_match('/(\d{9})/', (string) $val, $m)) {
            return $m[1];
        }

        return '100000001';
    }

    private function importarR2010(array $row, int $competenciaId, string $layout = 'simples'): bool
    {
        return $this->inserirR2010Normalizado(
            $this->normalizarLinhaR2010($row, $layout),
            $competenciaId
        );
    }

    private function inserirR2010Normalizado(array $norm, int $competenciaId): bool
    {
        if ($norm['cnpj_prestador'] === '' || strlen($norm['cnpj_prestador']) < 11) {
            return false;
        }

        $this->eventos->inserir('r2010', [
            'competencia_id'         => $competenciaId,
            'cnpj_prestador'         => $norm['cnpj_prestador'],
            'razao_social_prestador' => $norm['razao_social'],
            'tipo_insc_prestador'    => $norm['tipo_insc'],
            'num_documento'          => $norm['num_documento'],
            'serie'                  => $norm['serie'],
            'data_emissao'           => $norm['data_emissao'],
            'valor_bruto'            => $norm['valor_bruto'],
            'valor_base_retencao'    => $norm['valor_base'],
            'valor_retencao'         => $norm['valor_retencao'],
            'valor_desc_senar'       => $norm['valor_senar'],
            'cod_servico'            => $norm['cod_servico'],
            'ind_cprb'               => $norm['ind_cprb'],
        ]);

        return true;
    }

    // ─── R-2055 ──────────────────────────────────────────────

    /**
     * @return array{0: list<array>}
     */
    private function carregarLinhasR2055(string $arquivo): array
    {
        $spreadsheet = IOFactory::load($arquivo);
        $sheet       = $this->encontrarAbaR2055($spreadsheet);
        $rows        = $sheet->toArray(null, true, true, true);
        array_shift($rows);

        return [$rows];
    }

    private function encontrarAbaR2055(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $titulo = mb_strtolower($sheet->getTitle());
            $titulo = str_replace(['ç', 'ã', 'á', 'é', 'í', 'ó', 'ú'], ['c', 'a', 'a', 'e', 'i', 'o', 'u'], $titulo);
            if (str_contains($titulo, 'r2055') || str_contains($titulo, 'aquisicao')) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return array{
     *   cnpj_empresa: string,
     *   tp_insc_adquirente: string,
     *   nr_insc_adquirente: string,
     *   tp_insc_produtor: string,
     *   nr_insc_produtor: string,
     *   ind_opc_cp: ?string,
     *   ind_aquis: string,
     *   valor_bruto: float,
     *   valor_cp_desc: float,
     *   valor_rat_desc: float,
     *   valor_senar_desc: float,
     *   periodo: ?string
     * }
     */
    private function normalizarLinhaR2055(array $row): array
    {
        $cnpjEmpresa = preg_replace('/\D/', '', (string) ($row['A'] ?? ''));
        $adq         = $this->normalizarInscricao((string) ($row['B'] ?? ''), ['1', '3'], '1');
        $prod        = $this->normalizarInscricao((string) ($row['C'] ?? ''), ['1', '2'], null);

        $indOpc = strtoupper(trim((string) ($row['E'] ?? '')));
        $indOpc = $indOpc === 'S' ? 'S' : null;

        $indAquis = preg_replace('/\D/', '', (string) ($row['F'] ?? ''));
        if ($indAquis === '') {
            // CNPJ produtor → 3; CPF → 1 (regras de validação do leiaute)
            $indAquis = ($prod['tp'] === '1') ? '3' : '1';
        }

        return [
            'cnpj_empresa'       => $cnpjEmpresa,
            'tp_insc_adquirente' => $adq['tp'],
            'nr_insc_adquirente' => $adq['nr'],
            'tp_insc_produtor'   => $prod['tp'],
            'nr_insc_produtor'   => $prod['nr'],
            'ind_opc_cp'         => $indOpc,
            'ind_aquis'          => $indAquis,
            'valor_bruto'        => $this->parseMoeda($row['G'] ?? 0),
            'valor_cp_desc'      => $this->parseMoeda($row['H'] ?? 0),
            'valor_rat_desc'     => $this->parseMoeda($row['I'] ?? 0),
            'valor_senar_desc'   => $this->parseMoeda($row['J'] ?? 0),
            'periodo'            => $this->periodoFromApuracao($row['D'] ?? null),
        ];
    }

    /**
     * Normaliza CPF/CNPJ (completa zeros à esquerda quando a planilha omite).
     *
     * @param list<string> $tpsPermitidos
     * @return array{tp: string, nr: string}
     */
    private function normalizarInscricao(string $raw, array $tpsPermitidos, ?string $tpForcado): array
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return ['tp' => $tpForcado ?? '1', 'nr' => ''];
        }

        if ($tpForcado === '3' || (strlen($digits) > 11 && in_array('3', $tpsPermitidos, true) && !in_array('1', $tpsPermitidos, true))) {
            return ['tp' => '3', 'nr' => str_pad(substr($digits, -14), 14, '0', STR_PAD_LEFT)];
        }

        if (strlen($digits) <= 11 && in_array('2', $tpsPermitidos, true)) {
            return ['tp' => '2', 'nr' => str_pad($digits, 11, '0', STR_PAD_LEFT)];
        }

        // CNPJ (planilhas costumam perder o zero à esquerda → 13 dígitos)
        $nr = str_pad(substr($digits, -14), 14, '0', STR_PAD_LEFT);
        $tp = in_array('1', $tpsPermitidos, true) ? '1' : ($tpsPermitidos[0] ?? '1');

        return ['tp' => $tp, 'nr' => $nr];
    }

    private function periodoFromApuracao(mixed $val): ?string
    {
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m');
        }

        $s = trim((string) ($val ?? ''));
        if ($s === '') {
            return null;
        }

        // MM/AAAA ou M/AAAA
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d', (int) $m[2], (int) $m[1]);
        }
        // AAAA-MM
        if (preg_match('/^(\d{4})-(\d{2})$/', $s, $m)) {
            return $m[1] . '-' . $m[2];
        }

        return $this->periodoFromData($val);
    }

    private function importarR2055(array $row, int $competenciaId): bool
    {
        return $this->inserirR2055Normalizado($this->normalizarLinhaR2055($row), $competenciaId);
    }

    private function inserirR2055Normalizado(array $norm, int $competenciaId): bool
    {
        if ($norm['nr_insc_produtor'] === '' || $norm['nr_insc_adquirente'] === '') {
            return false;
        }
        if ($norm['valor_bruto'] <= 0) {
            throw new \RuntimeException('Valor bruto deve ser maior que zero.');
        }

        $this->eventos->inserir('r2055', [
            'competencia_id'     => $competenciaId,
            'tp_insc_adquirente' => $norm['tp_insc_adquirente'],
            'nr_insc_adquirente' => $norm['nr_insc_adquirente'],
            'tp_insc_produtor'   => $norm['tp_insc_produtor'],
            'nr_insc_produtor'   => $norm['nr_insc_produtor'],
            'ind_opc_cp'         => $norm['ind_opc_cp'],
            'ind_aquis'          => $norm['ind_aquis'],
            'valor_bruto'        => $norm['valor_bruto'],
            'valor_cp_desc'      => $norm['valor_cp_desc'],
            'valor_rat_desc'     => $norm['valor_rat_desc'],
            'valor_senar_desc'   => $norm['valor_senar_desc'],
        ]);

        return true;
    }

    // ─── R-2020 ──────────────────────────────────────────────

    private function importarR2020(array $row, int $competenciaId): bool
    {
        $this->eventos->inserir('r2020', [
            'competencia_id'      => $competenciaId,
            'cnpj_tomador'        => preg_replace('/\D/', '', $row['A'] ?? ''),
            'razao_social_tomador'=> $row['B'] ?? '',
            'tipo_insc_tomador'   => $row['C'] ?? '1',
            'num_documento'       => $row['D'] ?? '',
            'data_emissao'        => $this->parseData($row['E'] ?? null),
            'valor_bruto'         => $this->parseMoeda($row['F'] ?? 0),
            'valor_retencao'      => $this->parseMoeda($row['G'] ?? 0),
        ]);
        return true;
    }

    // ─── R-2060 ──────────────────────────────────────────────

    private function importarR2060(array $row, int $competenciaId): bool
    {
        $recBruta   = $this->parseMoeda($row['B'] ?? 0);
        $exclusoes  = $this->parseMoeda($row['C'] ?? 0);
        $aliquota   = $this->parseMoeda($row['D'] ?? 0);
        $base       = $recBruta - $exclusoes;
        $cprb       = round($base * ($aliquota / 100), 2);

        $this->eventos->inserir('r2060', [
            'competencia_id'     => $competenciaId,
            'cnae'               => $row['A'] ?? '',
            'valor_rec_bruta'    => $recBruta,
            'valor_exclusoes'    => $exclusoes,
            'valor_base_calculo' => $base,
            'aliquota'           => $aliquota,
            'valor_cprb'         => $cprb,
        ]);
        return true;
    }

    // ─── R-4010 ──────────────────────────────────────────────

    private function importarR4010(array $row, int $competenciaId): bool
    {
        $this->eventos->inserir('r4010', [
            'competencia_id'      => $competenciaId,
            'cpf_beneficiario'    => preg_replace('/\D/', '', $row['A'] ?? ''),
            'nome_beneficiario'   => $row['B'] ?? '',
            'natureza_rendimento' => $row['C'] ?? '',
            'data_pagamento'      => $this->parseData($row['D'] ?? null),
            'valor_bruto'         => $this->parseMoeda($row['E'] ?? 0),
            'valor_base_ir'       => $this->parseMoeda($row['F'] ?? 0),
            'valor_ir'            => $this->parseMoeda($row['G'] ?? 0),
            'valor_deducao'       => $this->parseMoeda($row['H'] ?? 0),
        ]);
        return true;
    }

    // ─── R-4020 ──────────────────────────────────────────────

    private function importarR4020(array $row, int $competenciaId): bool
    {
        // Formato oficial (planilha RFB - 22 colunas):
        // A=CNPJ Contribuinte, B=CNPJ Prestador, C=Nº NFS, D=Período Apuração,
        // E=Data Fato Gerador, F=Valor Bruto, G=Cod Tipo Serviço, H=Cód País,
        // I=Base Cálculo, J=IRRF, K=CSRF agregado, L=CSLL, M=PIS, N=COFINS,
        // O=Identificador, P=Ind FCI/SCP, Q=CNPJ FCI/SCP, R=% SCP,
        // S=Ind Judicial, T=Nº Processo, U=Ind Origem, V=Observações

        $cnpjBenef = preg_replace('/\D/', '', (string) ($row['B'] ?? ''));

        if ($cnpjBenef === '' || strlen($cnpjBenef) < 11) {
            return false;
        }

        $codTipoServico = str_pad(trim((string) ($row['G'] ?? '')), 5, '0', STR_PAD_LEFT);
        $natRend        = $codTipoServico;

        $vlrBruto  = $this->parseMoeda($row['F'] ?? 0);
        $vlrCsll   = $this->parseMoeda($row['L'] ?? 0);
        $vlrPis    = $this->parseMoeda($row['M'] ?? 0);
        $vlrCofins = $this->parseMoeda($row['N'] ?? 0);
        $vlrAgreg  = $this->parseMoeda($row['K'] ?? 0);

        $this->eventos->inserir('r4020', [
            'competencia_id'            => $competenciaId,
            'cnpj_contribuinte'         => preg_replace('/\D/', '', (string) ($row['A'] ?? '')) ?: null,
            'cnpj_beneficiario'         => $cnpjBenef,
            'num_nfs'                   => (string) ($row['C'] ?? ''),
            'periodo_apuracao'          => $this->parseData($row['D'] ?? null),
            'natureza_rendimento'       => $natRend,
            'cod_tipo_servico'          => $codTipoServico,
            'cod_pais'                  => (string) ($row['H'] ?? '') ?: null,
            'data_pagamento'            => $this->parseData($row['E'] ?? null),
            'valor_bruto'               => $vlrBruto,
            'valor_base_ir'             => $this->parseMoeda($row['I'] ?? 0) ?: $vlrBruto,
            'valor_base_csll'           => $vlrCsll > 0 ? $vlrBruto : 0,
            'valor_base_cofins'         => $vlrCofins > 0 ? $vlrBruto : 0,
            'valor_base_pis'            => $vlrPis > 0 ? $vlrBruto : 0,
            'valor_base_agreg'          => $vlrAgreg > 0 ? $vlrBruto : 0,
            'valor_ir'                  => $this->parseMoeda($row['J'] ?? 0),
            'vl_csrf_agregado'          => $vlrAgreg,
            'valor_csll'                => $vlrCsll,
            'valor_pis'                 => $vlrPis,
            'valor_cofins'              => $vlrCofins,
            'identificador_adicional'   => (string) ($row['O'] ?? '') ?: null,
            'indicador_fci_scp'         => !empty($row['P']) ? (int) $row['P'] : null,
            'cnpj_fci_scp'              => preg_replace('/\D/', '', (string) ($row['Q'] ?? '')) ?: null,
            'percentual_scp'            => !empty($row['R']) ? (float) $row['R'] : null,
            'indicador_judicial'        => !empty($row['S']) ? 1 : 0,
            'numero_processo'           => (string) ($row['T'] ?? '') ?: null,
            'indicador_origem_recurso'  => !empty($row['U']) ? (int) $row['U'] : null,
            'observacoes'               => (string) ($row['V'] ?? '') ?: null,
        ]);
        return true;
    }

    private function parseMoeda(mixed $val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }

        $s = trim((string) $val);
        if ($s === '' || strtoupper($s) === 'NULL') {
            return 0.0;
        }
        if (is_numeric($s)) {
            return (float) $s;
        }

        // Remove símbolo de moeda / espaços
        $s = preg_replace('/[R$\s\x{00A0}]/u', '', $s) ?? $s;

        // Ambos separadores: o último é o decimal
        if (str_contains($s, ',') && str_contains($s, '.')) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                // BR: 1.234,56
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // US: 1,234.56
                $s = str_replace(',', '', $s);
            }
            return (float) $s;
        }

        // Só vírgula → decimal BR (1.234,56 sem ponto já tratado; ou 1234,56)
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return (float) $s;
        }

        // Só ponto ou dígitos
        return (float) $s;
    }

    private function parseData(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d');
        }

        if (is_numeric($val)) {
            $unix = ((int) $val - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }

        $s = trim((string) $val);

        // M/D/YYYY ou D/M/YYYY — se o primeiro número > 12, é D/M/Y
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $s, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            if ($a > 12) {
                // D/M/Y
                return sprintf('%04d-%02d-%02d', $y, $b, $a);
            }
            if ($b > 12) {
                // M/D/Y
                return sprintf('%04d-%02d-%02d', $y, $a, $b);
            }
            // Ambíguo: assume M/D/Y (formato comum em planilhas US / Numbers)
            return sprintf('%04d-%02d-%02d', $y, $a, $b);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
