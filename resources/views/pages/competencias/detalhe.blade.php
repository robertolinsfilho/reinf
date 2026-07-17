@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : ($flash['tipo'] === 'erro' ? 'danger' : $flash['tipo']) }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5>{{ $competencia['razao_social'] }} — {{ $competencia['periodo'] }}</h5>
    <div class="d-flex gap-2">
        @php
        $cores = ['aberto'=>'secondary','fechado'=>'warning','transmitido'=>'success','retificado'=>'info'];
        @endphp
        <span class="badge bg-{{ $cores[$competencia['status']] ?? 'secondary' }} align-self-center">{{ ucfirst($competencia['status']) }}</span>
        <a href="/competencias" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<!-- Ações -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/importar?competencia_id={{ $competencia['id'] }}" class="btn btn-outline-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i> Importar Excel
    </a>
    <a href="/gerar?competencia_id={{ $competencia['id'] }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-code me-1"></i> Gerar XML
    </a>
    <a href="/transmissao?competencia_id={{ $competencia['id'] }}" class="btn btn-outline-info btn-sm">
        <i class="bi bi-send me-1"></i> Transmitir
    </a>
</div>

<!-- Resumo rápido -->
<div class="row g-2 mb-3">
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-2010</small><strong>{{ number_format($r2010_total ?? 0, 0, ',', '.') }}</strong></div></div>
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-2020</small><strong>{{ number_format($r2020_total ?? 0, 0, ',', '.') }}</strong></div></div>
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-2055</small><strong>{{ number_format($r2055_total ?? 0, 0, ',', '.') }}</strong></div></div>
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-2060</small><strong>{{ number_format($r2060_total ?? 0, 0, ',', '.') }}</strong></div></div>
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-4010</small><strong>{{ number_format($r4010_total ?? 0, 0, ',', '.') }}</strong></div></div>
    <div class="col"><div class="card p-3 text-center"><small class="text-muted d-block">R-4020</small><strong>{{ number_format($r4020_total ?? 0, 0, ',', '.') }}</strong></div></div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab-r2010">
            R-2010 <span class="badge bg-secondary ms-1">{{ number_format($r2010_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r2020">
            R-2020 <span class="badge bg-secondary ms-1">{{ number_format($r2020_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r2055">
            R-2055 <span class="badge bg-secondary ms-1">{{ number_format($r2055_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r2060">
            R-2060 <span class="badge bg-secondary ms-1">{{ number_format($r2060_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r4010">
            R-4010 <span class="badge bg-secondary ms-1">{{ number_format($r4010_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r4020">
            R-4020 <span class="badge bg-secondary ms-1">{{ number_format($r4020_total ?? 0, 0, ',', '.') }}</span>
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- R-2010 -->
    <div class="tab-pane active" id="tab-r2010">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r2010_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r2010_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r2010={{ $r2010_page - 1 }}#tab-r2010" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r2010_page }}/{{ $r2010_pages }}</span>
                            @if($r2010_page < $r2010_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r2010={{ $r2010_page + 1 }}#tab-r2010" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r2010?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-2010
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNPJ Prestador</th><th>Razão Social</th><th>Doc.</th><th class="text-end">Bruto</th><th class="text-end">Retenção</th></tr></thead>
                    <tbody>
                        @if(empty($r2010))
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r2010 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cnpj_prestador'] }}</td>
                            <td style="font-size:.82rem">{{ $r['razao_social_prestador'] ?? '' }}</td>
                            <td class="small">{{ $r['num_documento'] ?? '' }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_bruto'], 2, ',', '.') }}</td>
                            <td class="text-end small text-danger">R$ {{ number_format($r['valor_retencao'], 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-2020 -->
    <div class="tab-pane" id="tab-r2020">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r2020_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r2020_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r2020={{ $r2020_page - 1 }}#tab-r2020" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r2020_page }}/{{ $r2020_pages }}</span>
                            @if($r2020_page < $r2020_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r2020={{ $r2020_page + 1 }}#tab-r2020" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r2020?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-2020
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNPJ Tomador</th><th>Razão Social</th><th>Doc.</th><th class="text-end">Bruto</th><th class="text-end">Retenção</th></tr></thead>
                    <tbody>
                        @if(empty($r2020))
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r2020 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cnpj_tomador'] }}</td>
                            <td style="font-size:.82rem">{{ $r['razao_social_tomador'] ?? '' }}</td>
                            <td class="small">{{ $r['num_documento'] ?? '' }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_bruto'], 2, ',', '.') }}</td>
                            <td class="text-end small text-danger">R$ {{ number_format($r['valor_retencao'], 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-2055 -->
    <div class="tab-pane" id="tab-r2055">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r2055_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r2055_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r2055={{ $r2055_page - 1 }}#tab-r2055" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r2055_page }}/{{ $r2055_pages }}</span>
                            @if($r2055_page < $r2055_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r2055={{ $r2055_page + 1 }}#tab-r2055" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r2055?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-2055
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Adquirente</th><th>Produtor</th><th>indAquis</th><th class="text-end">Bruto</th><th class="text-end">CP</th><th class="text-end">SENAR</th></tr></thead>
                    <tbody>
                        @if(empty($r2055))
                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r2055 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['nr_insc_adquirente'] }}</td>
                            <td class="font-monospace small">{{ $r['nr_insc_produtor'] }}</td>
                            <td class="small">{{ $r['ind_aquis'] }}</td>
                            <td class="text-end small">R$ {{ number_format((float) $r['valor_bruto'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format((float) $r['valor_cp_desc'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format((float) $r['valor_senar_desc'], 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-2060 -->
    <div class="tab-pane" id="tab-r2060">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r2060_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r2060_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r2060={{ $r2060_page - 1 }}#tab-r2060" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r2060_page }}/{{ $r2060_pages }}</span>
                            @if($r2060_page < $r2060_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r2060={{ $r2060_page + 1 }}#tab-r2060" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r2060?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-2060
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNAE</th><th class="text-end">Rec. Bruta</th><th class="text-end">Exclusões</th><th class="text-end">Base</th><th>Alíq.</th><th class="text-end">CPRB</th></tr></thead>
                    <tbody>
                        @if(empty($r2060))
                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r2060 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cnae'] }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_rec_bruta'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_exclusoes'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_base_calculo'], 2, ',', '.') }}</td>
                            <td class="small">{{ number_format($r['aliquota'], 2, ',', '.') }}%</td>
                            <td class="text-end small text-danger">R$ {{ number_format($r['valor_cprb'], 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-4010 -->
    <div class="tab-pane" id="tab-r4010">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r4010_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r4010_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r4010={{ $r4010_page - 1 }}#tab-r4010" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r4010_page }}/{{ $r4010_pages }}</span>
                            @if($r4010_page < $r4010_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r4010={{ $r4010_page + 1 }}#tab-r4010" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r4010?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-4010
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CPF</th><th>Beneficiário</th><th>Nat.</th><th>Data</th><th class="text-end">Bruto</th><th class="text-end">IR</th></tr></thead>
                    <tbody>
                        @if(empty($r4010))
                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r4010 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cpf_beneficiario'] }}</td>
                            <td style="font-size:.82rem">{{ $r['nome_beneficiario'] ?? '' }}</td>
                            <td class="font-monospace small">{{ $r['natureza_rendimento'] }}</td>
                            <td class="small">{{ $r['data_pagamento'] ? date('d/m/Y', strtotime($r['data_pagamento'])) : '' }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_bruto'], 2, ',', '.') }}</td>
                            <td class="text-end small text-danger">R$ {{ number_format($r['valor_ir'], 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-4020 -->
    <div class="tab-pane" id="tab-r4020">
        <div class="card border-top-0 rounded-0 rounded-bottom">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center p-2">
                    <div>
                        @if(($r4020_pages ?? 1) > 1)
                        <div class="btn-group btn-group-sm">
                            @if($r4020_page > 1)
                            <a href="?id={{ $competencia['id'] }}&page_r4020={{ $r4020_page - 1 }}#tab-r4020" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                            @endif
                            <span class="btn btn-outline-secondary disabled">Pág {{ $r4020_page }}/{{ $r4020_pages }}</span>
                            @if($r4020_page < $r4020_pages)
                            <a href="?id={{ $competencia['id'] }}&page_r4020={{ $r4020_page + 1 }}#tab-r4020" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <a href="/eventos/r4020?competencia_id={{ $competencia['id'] }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Gerenciar R-4020
                    </a>
                </div>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNPJ</th><th>Beneficiário</th><th>Data</th><th class="text-end">Bruto</th><th class="text-end">IR</th><th class="text-end">CSLL</th><th class="text-end">PIS/COF</th></tr></thead>
                    <tbody>
                        @if(empty($r4020))
                        <tr><td colspan="7" class="text-center text-muted py-3">Nenhum registro.</td></tr>
                        @else
                        @foreach($r4020 as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cnpj_beneficiario'] }}</td>
                            <td style="font-size:.82rem">{{ $r['razao_social_beneficiario'] ?? '' }}</td>
                            <td class="small">{{ $r['data_pagamento'] ? date('d/m/Y', strtotime($r['data_pagamento'])) : '' }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_bruto'], 2, ',', '.') }}</td>
                            <td class="text-end small text-danger">R$ {{ number_format($r['valor_ir'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_csll'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format(($r['valor_pis'] ?? 0) + ($r['valor_cofins'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
// Após reload, ativar a tab conforme hash na URL
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const tabTrigger = document.querySelector(`a[href="${window.location.hash}"]`);
        if (tabTrigger) {
            new bootstrap.Tab(tabTrigger).show();
        }
    }
});
</script>
</div>
