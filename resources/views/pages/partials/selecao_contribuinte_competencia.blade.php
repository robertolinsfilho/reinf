@php
    /** @var list<array{contribuinte_id:int,razao_social:string,cnpj:string,competencias:list<array>}> $grupos */
    /** @var string $basePath ex: /gerar ou /transmissao */
    /** @var string $acaoLabel */
    /** @var string $acaoIcon */
    /** @var string $titulo */
    /** @var string $paramName query param for competência id */
    $grupos     = $grupos ?? [];
    $basePath   = $basePath ?? '/gerar';
    $paramName  = $paramName ?? 'competencia_id';
    $acaoLabel  = $acaoLabel ?? 'Continuar';
    $acaoIcon   = $acaoIcon ?? 'bi-arrow-right';
    $titulo     = $titulo ?? 'Selecione o contribuinte e a competência';
    $accId      = 'acc-' . preg_replace('/[^a-z0-9]/', '', strtolower($basePath));
@endphp
<div class="card">
    <div class="card-header"><i class="bi bi-building me-2"></i>{{ $titulo }}</div>
    <div class="card-body">
        @if(empty($grupos))
        <div class="text-center text-muted py-4">
            Nenhuma competência cadastrada.
            <div class="mt-2"><a href="/competencias/nova" class="btn btn-primary btn-sm">Criar competência</a></div>
        </div>
        @else
        <p class="text-muted small mb-3">Clique no contribuinte e escolha o período.</p>
        <div class="accordion" id="{{ $accId }}">
            @foreach($grupos as $i => $g)
            @php
                $cid = (int) $g['contribuinte_id'];
                $itemId = $accId . '-c' . $cid;
                $qtd = count($g['competencias']);
                $cnpjFmt = preg_replace('/\D/', '', $g['cnpj'] ?? '');
                if (strlen($cnpjFmt) === 14) {
                    $cnpjFmt = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpjFmt);
                }
            @endphp
            <div class="accordion-item">
                <h2 class="accordion-header" id="h-{{ $itemId }}">
                    <button class="accordion-button collapsed py-3" type="button"
                            data-bs-toggle="collapse" data-bs-target="#{{ $itemId }}"
                            aria-expanded="false" aria-controls="{{ $itemId }}">
                        <span class="me-auto">
                            <strong>{{ $g['razao_social'] }}</strong>
                            @if($cnpjFmt !== '')
                            <span class="text-muted font-monospace small ms-2">{{ $cnpjFmt }}</span>
                            @endif
                        </span>
                        <span class="badge bg-secondary me-2">{{ $qtd }} competência{{ $qtd === 1 ? '' : 's' }}</span>
                    </button>
                </h2>
                <div id="{{ $itemId }}" class="accordion-collapse collapse"
                     data-bs-parent="#{{ $accId }}" aria-labelledby="h-{{ $itemId }}">
                    <div class="accordion-body">
                        <form method="GET" action="{{ $basePath }}" class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Competência (período)</label>
                                <select name="{{ $paramName }}" class="form-select" required>
                                    <option value="">Selecione o período…</option>
                                    @foreach($g['competencias'] as $c)
                                    <option value="{{ (int) $c['id'] }}">
                                        {{ $c['periodo'] }}
                                        — {{ $c['status'] ?? '' }}
                                        @if(!empty($c['total_r4020']))
                                        · R-4020: {{ (int) $c['total_r4020'] }}
                                        @endif
                                        @if(!empty($c['total_r2010']))
                                        · R-2010: {{ (int) $c['total_r2010'] }}
                                        @endif
                                        @if(!empty($c['total_r2055']))
                                        · R-2055: {{ (int) $c['total_r2055'] }}
                                        @endif
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi {{ $acaoIcon }} me-1"></i>
                                    {{ $acaoLabel }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
