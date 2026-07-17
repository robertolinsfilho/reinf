@if(!empty($flash))
<div class="alert alert-{{ ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : (($flash['tipo'] ?? '') === 'erro' || ($flash['tipo'] ?? '') === 'danger' ? 'danger' : 'info') }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-file-earmark-code me-2"></i>Gerar XML EFD-REINF</h5>
    @if($competencia)
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center">{{ $competencia['razao_social'] }} | {{ $competencia['periodo'] }}</span>
        <a href="/gerar" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Trocar competência</a>
    </div>
    @endif
</div>

@if(!$competencia)
@include('pages.partials.selecao_contribuinte_competencia', [
    'grupos'    => $gruposContribuintes ?? [],
    'basePath'  => '/gerar',
    'acaoLabel' => 'Gerar XML',
    'acaoIcon'  => 'bi-file-earmark-code',
    'titulo'    => 'Selecione o contribuinte',
])
@else

@if(empty($contatoR1000Ok))
<div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div>
        <strong>Cadastro R-1000 incompleto.</strong>
        Preencha no contribuinte: classificação tributária (Tabela 08), indicadores, nome/CPF do contato e telefone com DDD.
        <a href="/contribuintes/editar?id={{ (int) $competencia['contribuinte_id'] }}" class="alert-link">Editar contribuinte</a>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Selecionar Eventos</div>
            <div class="card-body p-4">
                <form action="/gerar/xml" method="POST">
                    @csrf
                    <input type="hidden" name="competencia_id" value="{{ $competencia['id'] }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Eventos a gerar</label>
                        <div class="d-grid gap-2">
                            @php
                            $eventosList = [
                                'R1000' => ['R-1000 – Informações do Contribuinte', true],
                                'R1070' => ['R-1070 – Processos Admin./Judiciais', true],
                                'R2010' => ['R-2010 – Retenções INSS (Tomados)', $eventosDisponiveis['R2010'] ?? false],
                                'R2020' => ['R-2020 – Retenções INSS (Prestados)', $eventosDisponiveis['R2020'] ?? false],
                                'R2055' => ['R-2055 – Aquisição Prod. Rural', $eventosDisponiveis['R2055'] ?? false],
                                'R2060' => ['R-2060 – CPRB', $eventosDisponiveis['R2060'] ?? false],
                                'R4010' => ['R-4010 – Pagamentos PF (IRRF)', $eventosDisponiveis['R4010'] ?? false],
                                'R4020' => ['R-4020 – Pagamentos PJ (IRRF/CSRF)', $eventosDisponiveis['R4020'] ?? false],
                                'R2099' => ['R-2099 – Fechamento Série R-2000', true],
                                'R4099' => ['R-4099 – Fechamento Série R-4000', true],
                            ];
                            @endphp
                            @foreach($eventosList as $cod => [$desc, $temDados])
                            @php
                                $disabled = !$temDados && !in_array($cod, ['R1000','R1070','R2099','R4099'], true);
                            @endphp
                            <div class="form-check border rounded p-3 ps-5 {{ $disabled ? 'opacity-50' : '' }}" style="cursor:{{ $disabled ? 'not-allowed' : 'pointer' }}">
                                <input class="form-check-input" type="checkbox" name="eventos[]"
                                       value="{{ $cod }}" id="ev-{{ $cod }}"
                                       {{ $disabled ? 'disabled' : '' }}
                                       {{ ($temDados && !in_array($cod, ['R1000','R2099','R4099'], true)) ? 'checked' : '' }}>
                                <label class="form-check-label w-100" for="ev-{{ $cod }}" style="cursor:inherit">
                                    <span class="badge bg-primary me-1">{{ $cod }}</span>
                                    <span style="font-size:.85rem">{{ $desc }}</span>
                                    @if($temDados && !in_array($cod, ['R1000','R2099','R4099'], true))
                                        <i class="bi bi-check-circle-fill text-success ms-1" title="Tem registros"></i>
                                    @elseif($disabled)
                                        <small class="text-muted ms-1">(sem registros)</small>
                                    @endif
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="form-text mt-2">
                            Para excluir evento já aceito na RFB, use <a href="/transmissao?competencia_id={{ (int) $competencia['id'] }}">Transmissão → R-9000</a>.
                        </div>
                    </div>

                    <div class="mb-3 p-3 border rounded bg-light">
                        <label class="form-label fw-bold mb-2">
                            <i class="bi bi-arrow-repeat me-1"></i> Tipo de Operação
                        </label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ind_retif" id="retif-1" value="1" checked onchange="document.getElementById('campo-recibo').style.display='none'">
                            <label class="form-check-label" for="retif-1">
                                <strong>Inclusão</strong> — primeira transmissão deste evento
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ind_retif" id="retif-2" value="2" onchange="document.getElementById('campo-recibo').style.display='block'">
                            <label class="form-check-label" for="retif-2">
                                <strong>Retificação</strong> — corrigir evento já transmitido
                            </label>
                        </div>
                        <div id="campo-recibo" style="display:none" class="mt-2">
                            <label class="form-label small mb-1">Número do Recibo Original</label>
                            @php $recibosSalvos = $recibosSalvos ?? ($recibosR4020 ?? []); @endphp
                            @if(!empty($recibosSalvos))
                            <select class="form-select form-select-sm font-monospace mb-2"
                                    onchange="document.getElementById('nr-recibo-input').value=this.value">
                                <option value="">Usar recibo salvo automaticamente (por evento)</option>
                                @foreach($recibosSalvos as $r)
                                @php
                                    $detalhe = '';
                                    $xml = (string) ($r['xml_conteudo'] ?? '');
                                    if (($r['evento'] ?? '') === 'R4020' && preg_match('/<cnpjBenef>(\d+)<\/cnpjBenef>/', $xml, $mb)) {
                                        $detalhe = ' — CNPJ ' . $mb[1];
                                    } elseif (($r['evento'] ?? '') === 'R4010' && preg_match('/<cpfBenef>(\d+)<\/cpfBenef>/', $xml, $mb)) {
                                        $detalhe = ' — CPF ' . $mb[1];
                                    } elseif (($r['evento'] ?? '') === 'R2010' && preg_match('/<cnpjPrestador>(\d+)<\/cnpjPrestador>/', $xml, $mp)) {
                                        $detalhe = ' — prestador ' . $mp[1];
                                    } elseif (($r['evento'] ?? '') === 'R2055' && preg_match('/<nrInscProd>(\d+)<\/nrInscProd>/', $xml, $mp)) {
                                        $detalhe = ' — produtor ' . $mp[1];
                                    }
                                @endphp
                                <option value="{{ $r['nr_recibo_retornado'] }}">
                                    [{{ $r['evento'] ?? '?' }}]
                                    {{ $r['nr_recibo_retornado'] }}
                                    {{ $detalhe }}
                                    ({{ $r['nome_arquivo'] }})
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text mb-2">
                                Em branco: R-4020/R-2055 usam recibo por beneficiário/produtor;
                                R-2010/R-2020/R-2060/R-4010 usam o último recibo do evento na competência.
                            </div>
                            @endif
                            <input type="text" name="nr_recibo_original" id="nr-recibo-input" class="form-control form-control-sm font-monospace" placeholder="Recibo da transmissão original">
                            <div class="form-text">Informe o recibo retornado pela Receita Federal (ou selecione acima).</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3 p-3 ps-5 border rounded bg-light">
                        <input class="form-check-input" type="checkbox" name="assinar" id="chk-assinar" value="1"
                               {{ ($certInfo['valido'] ?? false) ? 'checked' : 'disabled' }}>
                        <label class="form-check-label" for="chk-assinar">
                            <i class="bi bi-pen me-1"></i> Assinar digitalmente (certificado A1)
                        </label>
                        @if(!($certInfo['valido'] ?? false))
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Certificado não configurado. <a href="/certificados">Configurar</a>
                        </div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-file-earmark-code me-1"></i> Gerar Arquivos XML
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-archive me-2"></i>Arquivos Gerados</span>
                @if(!empty($arquivosGerados))
                <a href="/transmissao?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-send me-1"></i> Transmitir
                </a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Evento</th><th>Tipo</th><th>Arquivo</th><th>Recibo</th><th>Tamanho</th><th>Assinado</th><th>Gerado em</th><th></th></tr>
                    </thead>
                    <tbody>
                        @if(empty($arquivosGerados))
                        <tr><td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
                            Nenhum arquivo gerado para esta competência.
                        </td></tr>
                        @else
                        @foreach($arquivosGerados as $a)
                        <tr>
                            <td><span class="badge bg-primary">{{ $a['evento'] ?? '—' }}</span></td>
                            <td>
                                @if(($a['ind_retif'] ?? 1) == 2)
                                    <span class="badge bg-warning" title="Recibo: {{ $a['nr_recibo_original'] ?? '' }}">
                                        <i class="bi bi-arrow-repeat"></i> Retificação
                                    </span>
                                @else
                                    <span class="badge bg-success">Inclusão</span>
                                @endif
                            </td>
                            <td class="font-monospace small text-primary">{{ $a['nome_arquivo'] }}</td>
                            <td class="font-monospace small">
                                @if(!empty($a['nr_recibo_retornado']))
                                    <span class="text-success" title="Protocolo: {{ $a['protocolo'] ?? '' }}">
                                        {{ $a['nr_recibo_retornado'] }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ number_format(($a['tamanho'] ?? 0) / 1024, 1) }} KB</td>
                            <td>{!! !empty($a['assinado']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' !!}</td>
                            <td class="small text-muted">{{ date('d/m/Y H:i', strtotime($a['created_at'])) }}</td>
                            <td>
                                <a href="/download?id={{ $a['id'] }}" class="btn btn-sm btn-outline-success py-0">
                                    <i class="bi bi-download"></i>
                                </a>
                                @if(!empty($a['nr_recibo_retornado']))
                                <a href="/transmissao?competencia_id={{ (int) $competencia['id'] }}"
                                   class="btn btn-sm btn-outline-danger py-0"
                                   title="Já transmitido — exclua na RFB via R-9000 na Transmissão">
                                    <i class="bi bi-trash3"></i>
                                </a>
                                @else
                                <form action="/transmissao/excluir-arquivos" method="POST" class="d-inline"
                                      onsubmit="return confirm('Apagar este XML localmente?')">
                                    @csrf
                                    <input type="hidden" name="competencia_id" value="{{ (int) $competencia['id'] }}">
                                    <input type="hidden" name="voltar" value="gerar">
                                    <input type="hidden" name="arquivos[]" value="{{ (int) $a['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Apagar local">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Informações</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>XMLs gerados conforme leiaute EFD-REINF <strong>v2.1.2</strong></li>
                    <li>Ambiente: <strong>{{ ($config['reinf']['tp_amb'] ?? 2) === 1 ? 'Produção' : 'Homologação' }}</strong></li>
                    <li>Após gerar, vá para <a href="/transmissao?competencia_id={{ $competencia['id'] }}"><strong>Transmissão</strong></a></li>
                    <li>Apagar XML local: ícone de lixeira nesta lista. Se já tiver recibo, use R-9000 na Transmissão.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endif
