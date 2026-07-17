@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5>R-2060 – CPRB</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center">{{ $competencia['razao_social'] }} | {{ $competencia['periodo'] }}</span>
        <a href="/competencias/detalhe?id={{ $competencia['id'] }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Adicionar Registro CPRB</div>
            <div class="card-body p-4">
                <form action="/eventos/r2060/salvar" method="POST">
                    @csrf
                    <input type="hidden" name="competencia_id" value="{{ $competencia['id'] }}">
                    <div class="mb-2">
                        <label class="form-label">CNAE *</label>
                        <input type="text" name="cnae" class="form-control font-monospace" placeholder="Ex: 6201-5/01" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Construção Civil?</label>
                        <select name="ind_constr_civil" class="form-select form-select-sm">
                            <option value="0">0 – Não</option>
                            <option value="1">1 – Sim</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Receita Bruta Total *</label>
                        <div class="input-group">
                            <span class="input-group-text small">R$</span>
                            <input type="text" name="valor_rec_bruta" id="rec-bruta" class="form-control text-end" placeholder="0,00" required oninput="calcBase()">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Exclusões da Base</label>
                        <div class="input-group">
                            <span class="input-group-text small">R$</span>
                            <input type="text" name="valor_rec_bruta_excl" id="excl" class="form-control text-end" placeholder="0,00" oninput="calcBase()">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Alíquota (%)</label>
                        <input type="text" name="aliquota" id="aliq" class="form-control text-end" placeholder="1,00" oninput="calcBase()">
                    </div>
                    <div class="mb-3 p-2 bg-light rounded">
                        <div class="d-flex justify-content-between small">
                            <span>Base de Cálculo:</span>
                            <span id="base-calc" class="fw-bold">R$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-danger">
                            <span>Contribuição estimada:</span>
                            <span id="contrib-est" class="fw-bold">R$ 0,00</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Adicionar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Registros <span class="badge bg-secondary ms-1">{{ count($registros) }}</span></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNAE</th><th class="text-end">Rec. Bruta</th><th class="text-end">Excl.</th><th class="text-end">Base</th><th class="text-end">Alíq.</th><th class="text-end">Contrib.</th><th></th></tr></thead>
                    <tbody>
                        @if(empty($registros))
                        <tr><td colspan="7" class="text-center text-muted py-4">Sem registros.</td></tr>
                        @else
                        @foreach($registros as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r['cnae'] }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_rec_bruta'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_rec_bruta_excl'], 2, ',', '.') }}</td>
                            <td class="text-end small">R$ {{ number_format($r['valor_base_calculo'], 2, ',', '.') }}</td>
                            <td class="text-end small">{{ $r['aliquota'] }}%</td>
                            <td class="text-end small text-danger fw-bold">R$ {{ number_format($r['valor_contribuicao'], 2, ',', '.') }}</td>
                            <td>
                                <form action="/eventos/r2060/excluir" method="POST" onsubmit="return confirm('Apagar este registro localmente?')">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ (int) $r['id'] }}">
                                    <input type="hidden" name="competencia_id" value="{{ (int) $competencia['id'] }}">
                                    <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Apagar local"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function parseMoeda(v) {
    return parseFloat(v.replace(/\./g,'').replace(',','.')) || 0;
}
function formatMoeda(v) {
    return 'R$ ' + v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}
function calcBase() {
    const bruta = parseMoeda(document.getElementById('rec-bruta').value);
    const excl  = parseMoeda(document.getElementById('excl').value);
    const aliq  = parseMoeda(document.getElementById('aliq').value);
    const base  = Math.max(bruta - excl, 0);
    const contrib = base * aliq / 100;
    document.getElementById('base-calc').textContent = formatMoeda(base);
    document.getElementById('contrib-est').textContent = formatMoeda(contrib);
}
</script>
