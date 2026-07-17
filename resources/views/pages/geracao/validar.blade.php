<div class="page-header">
    <h5><i class="bi bi-shield-exclamation text-warning me-2"></i>Validação XSD — Erros encontrados</h5>
    <a href="/gerar?competencia_id={{ $competenciaId }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Um ou mais XMLs falharam na validação contra o XSD oficial.</strong>
    Se você transmitir mesmo assim, a Receita Federal provavelmente vai <strong>rejeitar</strong>.
    Revise os erros, corrija os dados e tente gerar novamente.
</div>

@foreach($resultado['resultados'] as $r)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <span class="badge bg-primary me-1">{{ $r['evento'] }}</span>
            <span class="font-monospace small">{{ $r['nome'] }}</span>
        </span>
        @if($r['valido'])
            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Válido</span>
        @else
            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Inválido</span>
        @endif
    </div>
    <div class="card-body">
        @if(!empty($r['aviso']))
        <div class="alert alert-info py-2 small mb-2">
            <i class="bi bi-info-circle me-1"></i> {{ $r['aviso'] }}
        </div>
        @endif

        @if(!empty($r['erros']))
        <div class="bg-light p-3 rounded small">
            <strong class="text-danger">Erros de validação:</strong>
            <ul class="mb-0 mt-2">
                @foreach($r['erros'] as $erro)
                <li class="font-monospace small">{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
        @else
        <div class="text-success small"><i class="bi bi-check-circle me-1"></i> Sem erros.</div>
        @endif
    </div>
</div>
@endforeach

<div class="d-flex justify-content-between gap-2 mt-3">
    <a href="/gerar?competencia_id={{ $competenciaId }}" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise me-1"></i> Corrigir e gerar novamente
    </a>

    <form action="/gerar/xml" method="POST" class="d-inline">
        @csrf
        <input type="hidden" name="competencia_id" value="{{ $pendente['competencia_id'] }}">
        <input type="hidden" name="ind_retif" value="{{ $pendente['ind_retif'] }}">
        <input type="hidden" name="nr_recibo_original" value="{{ $pendente['nr_recibo_original'] ?? '' }}">
        @if($pendente['assinar'])<input type="hidden" name="assinar" value="1">@endif
        @foreach($pendente['eventos'] as $ev)
        <input type="hidden" name="eventos[]" value="{{ $ev }}">
        @endforeach
        <input type="hidden" name="forcar" value="1">
        <button type="submit" class="btn btn-outline-danger"
                onclick="return confirm('Tem certeza? Os XMLs estão inválidos e podem ser rejeitados pela RFB.')">
            <i class="bi bi-exclamation-triangle me-1"></i> Forçar geração (ignorar validação)
        </button>
    </form>
</div>
