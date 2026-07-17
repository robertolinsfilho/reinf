<?php

namespace App\Http\Controllers;

use App\Repositories\ArquivoGeradoRepository;
use App\Repositories\CompetenciaRepository;
use App\Repositories\EventoRepository;
use App\Services\AssinaturaService;
use App\Services\GeracaoXmlService;
use App\Services\ValidacaoService;
use App\Services\ValidacaoXmlService;
use Illuminate\Http\Request;

class GeracaoController extends Controller
{
    private CompetenciaRepository $competencias;
    private EventoRepository $eventos;
    private ArquivoGeradoRepository $arquivos;

    public function __construct()
    {
        parent::__construct();
        $this->competencias = new CompetenciaRepository($this->db);
        $this->eventos      = new EventoRepository($this->db);
        $this->arquivos     = new ArquivoGeradoRepository($this->db);
    }

    public function index(Request $request)
    {
        $compId = (int) $request->query('competencia_id');

        if (!$compId) {
            return $this->render('pages.geracao.index', [
                'pageTitle'           => 'Gerar XML',
                'competencia'         => null,
                'gruposContribuintes' => $this->competencias->listGroupedByContribuinte($this->userId()),
                'eventosDisponiveis'  => [],
                'arquivosGerados'     => [],
                'recibosSalvos'       => [],
                'certInfo'            => (new AssinaturaService($this->userId()))->infoCertificado(),
            ]);
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            return $this->flashRedirect('/gerar', 'Competência não encontrada.', 'erro');
        }

        $disponiveis = ['R1000' => true, 'R1070' => true];
        foreach (['R2010' => 'r2010', 'R2020' => 'r2020', 'R2055' => 'r2055', 'R2060' => 'r2060', 'R4010' => 'r4010', 'R4020' => 'r4020'] as $evt => $tab) {
            $disponiveis[$evt] = $this->eventos->contar($tab, $compId) > 0;
        }

        $cpfContato = preg_replace('/\D/', '', (string) ($comp['cpf_contato'] ?? '')) ?? '';
        $contatoR1000Ok = trim((string) ($comp['nome_contato'] ?? '')) !== ''
            && strlen($cpfContato) === 11
            && ValidacaoService::validarCpf($cpfContato);

        return $this->render('pages.geracao.index', [
            'pageTitle'           => 'Gerar XML',
            'competencia'         => $comp,
            'gruposContribuintes' => [],
            'eventosDisponiveis'  => $disponiveis,
            'arquivosGerados'     => $this->arquivos->listByCompetenciaForUser($compId, $this->userId()),
            'recibosSalvos'       => $this->arquivos->listRecibos($compId, null, $this->userId()),
            'certInfo'            => (new AssinaturaService($this->userId(), (int) $comp['contribuinte_id']))->infoCertificado(),
            'contatoR1000Ok'      => $contatoR1000Ok,
        ]);
    }

    public function gerar(Request $request)
    {
        $compId       = (int) $request->input('competencia_id');
        $selecionados = $request->input('eventos') ?? [];
        $assinar      = !empty($request->input('assinar'));
        $indRetif     = (int) ($request->input('ind_retif') ?: 1);
        $nrRecibo     = trim((string) $request->input('nr_recibo_original', '')) ?: null;
        $forcar       = !empty($request->input('forcar'));
        $url          = "/gerar?competencia_id={$compId}";

        if (!$compId || empty($selecionados)) {
            return $this->flashRedirect($url, 'Selecione ao menos um evento.', 'erro');
        }

        if ($nrRecibo !== null && !preg_match('/^[A-Za-z0-9.\\-\/]{1,52}$/', $nrRecibo)) {
            return $this->flashRedirect($url, 'Número de recibo inválido.', 'erro');
        }

        $recibosSalvos = [];
        $uid = $this->userId();
        foreach (['R4020', 'R2010', 'R2055', 'R2020', 'R2060', 'R4010'] as $evt) {
            if (in_array($evt, $selecionados, true)) {
                $recibosSalvos = array_merge($recibosSalvos, $this->arquivos->listRecibos($compId, $evt, $uid));
            }
        }

        if ($indRetif === 2 && !$nrRecibo && empty($recibosSalvos)) {
            return $this->flashRedirect($url, 'Retificação exige o número do recibo original (informe ou consulte o protocolo antes).', 'erro');
        }

        $comp = $this->competencias->findWithContribuinte($compId, $this->userId());
        if (!$comp) {
            return $this->flashRedirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        return $this->safeExecute(function () use ($comp, $compId, $selecionados, $assinar, $indRetif, $nrRecibo, $forcar, $url) {
            $arquivos = (new GeracaoXmlService($this->db))->gerar($comp, $selecionados, $indRetif, $nrRecibo);

            // Validar contra XSD
            $validador = new ValidacaoXmlService();
            $validacao = $validador->validarLote(array_map(fn ($a) => [
                'evento' => $a['evento'],
                'xml'    => $a['xml'],
                'nome'   => $a['nome'],
            ], $arquivos));

            // Se houver erro de validação e não estiver em modo "forçar", mostra tela de validação
            if (!$validacao['todos_validos'] && !$forcar) {
                session(['validacao_pendente' => [
                    'competencia_id'     => $compId,
                    'eventos'            => $selecionados,
                    'assinar'            => $assinar,
                    'ind_retif'          => $indRetif,
                    'nr_recibo_original' => $nrRecibo,
                    'resultado'          => $validacao,
                ]]);
                return redirect("/gerar/validar?competencia_id={$compId}");
            }

            // Assinar se solicitado
            if ($assinar) {
                $assinatura = new AssinaturaService($this->userId(), (int) ($comp['contribuinte_id'] ?? 0) ?: null);
                foreach ($arquivos as &$arq) {
                    $arq['xml']     = $assinatura->assinar($arq['xml']);
                    file_put_contents($arq['caminho'], $arq['xml']);
                    $arq['hash']    = md5_file($arq['caminho']);
                    $arq['tamanho'] = filesize($arq['caminho']);
                }
                unset($arq);
            }

            // Salvar no banco
            foreach ($arquivos as $arq) {
                $this->arquivos->salvar($compId, $this->userId(), $arq, $assinar, $indRetif, $nrRecibo);
            }

            session()->forget('validacao_pendente');

            $tipo  = $indRetif === 2 ? 'Retificação' : 'Inclusão';
            $qtd   = count($arquivos);
            $aviso = $validacao['tem_aviso'] ? ' (validação parcial — alguns XSDs não instalados)' : '';
            $msg   = "{$qtd} XML(s) de {$tipo} gerado(s)" . ($assinar ? ' e assinado(s)' : '') . "{$aviso}!";
            return $this->flashRedirect($url, $msg, 'sucesso');
        }, $url, 'Erro na geração');
    }

    /**
     * Tela de revisão quando a validação XSD falha.
     */
    public function validar(Request $request)
    {
        $compId   = (int) $request->query('competencia_id');
        $pendente = session('validacao_pendente');

        if (!$pendente) {
            return $this->flashRedirect("/gerar?competencia_id={$compId}", 'Nenhuma validação pendente.', 'erro');
        }

        return $this->render('pages.geracao.validar', [
            'pageTitle'     => 'Validação XSD',
            'resultado'     => $pendente['resultado'],
            'pendente'      => $pendente,
            'competenciaId' => $compId,
        ]);
    }

    /**
     * Tela de status dos XSDs instalados.
     */
    public function statusXsd()
    {
        $validador = new ValidacaoXmlService();
        return $this->render('pages.geracao.xsd', [
            'pageTitle' => 'Status dos Schemas XSD',
            'status'    => $validador->statusXsds(),
        ]);
    }

    public function download(Request $request)
    {
        $arquivo = $this->arquivos->findForUser((int) $request->query('id'), $this->userId());

        if (!$arquivo) {
            return response('Arquivo não encontrado.', 404);
        }

        $conteudo = file_exists($arquivo['caminho'])
            ? file_get_contents($arquivo['caminho'])
            : ($arquivo['xml_conteudo'] ?? '');

        if (!$conteudo) {
            return response('XML não disponível.', 404);
        }

        $nome = basename((string) $arquivo['nome_arquivo']);

        return response($conteudo, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
            'Content-Length'      => strlen($conteudo),
        ]);
    }
}
