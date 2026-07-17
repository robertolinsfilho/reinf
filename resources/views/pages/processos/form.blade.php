@if(!empty($flash))
<div class="alert alert-{{ ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-folder2-open me-2"></i>{{ $processo ? 'Editar Processo' : 'Novo Processo' }}</h5>
    <a href="/processos" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="/processos/salvar" method="POST">
            @csrf
            @if($processo)
            <input type="hidden" name="id" value="{{ $processo['id'] }}">
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Contribuinte *</label>
                    <select name="contribuinte_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        @foreach($contribuintes as $c)
                        <option value="{{ $c['id'] }}" {{ ($processo['contribuinte_id'] ?? '') == $c['id'] ? 'selected' : '' }}>
                            {{ $c['razao_social'] }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipo *</label>
                    <select name="tipo_processo" class="form-select" required onchange="document.getElementById('grupo-judicial').style.display = this.value == 2 ? 'flex' : 'none'">
                        <option value="1" {{ ($processo['tipo_processo'] ?? 1) == 1 ? 'selected' : '' }}>Administrativo</option>
                        <option value="2" {{ ($processo['tipo_processo'] ?? '') == 2 ? 'selected' : '' }}>Judicial</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="ativo" {{ ($processo['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="suspenso" {{ ($processo['status'] ?? '') === 'suspenso' ? 'selected' : '' }}>Suspenso</option>
                        <option value="encerrado" {{ ($processo['status'] ?? '') === 'encerrado' ? 'selected' : '' }}>Encerrado</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-bold">Número do Processo *</label>
                    <input type="text" name="numero_processo" class="form-control font-monospace"
                           value="{{ $processo['numero_processo'] ?? '' }}" required
                           placeholder="0001234-56.2024.4.03.6100">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Data Inclusão *</label>
                    <input type="date" name="data_inclusao" class="form-control"
                           value="{{ $processo['data_inclusao'] ?? date('Y-m-d') }}" required>
                </div>

                <!-- Grupo Judicial -->
                <div class="col-12" id="grupo-judicial" style="display:{{ ($processo['tipo_processo'] ?? 1) == 2 ? 'flex' : 'none' }};flex-wrap:wrap;gap:1rem">
                    <div style="flex:1;min-width:150px">
                        <label class="form-label">Autoria</label>
                        <select name="indicador_autoria" class="form-select">
                            <option value="1" {{ ($processo['indicador_autoria'] ?? 1) == 1 ? 'selected' : '' }}>1 - Próprio contribuinte</option>
                            <option value="2" {{ ($processo['indicador_autoria'] ?? '') == 2 ? 'selected' : '' }}>2 - Outros</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:80px">
                        <label class="form-label">UF da Vara</label>
                        <input type="text" name="uf_vara" class="form-control" maxlength="2"
                               value="{{ $processo['uf_vara'] ?? '' }}" placeholder="SP">
                    </div>
                    <div style="flex:1;min-width:120px">
                        <label class="form-label">Cód. Município</label>
                        <input type="text" name="cod_municipio" class="form-control" maxlength="7"
                               value="{{ $processo['cod_municipio'] ?? '' }}" placeholder="3550308">
                    </div>
                    <div style="flex:1;min-width:80px">
                        <label class="form-label">ID Vara</label>
                        <input type="text" name="id_vara" class="form-control" maxlength="4"
                               value="{{ $processo['id_vara'] ?? '' }}">
                    </div>
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Suspensão da Exigibilidade?</label>
                    <select name="indicador_susp_exig" class="form-select">
                        <option value="0" {{ !($processo['indicador_susp_exig'] ?? 0) ? 'selected' : '' }}>Não</option>
                        <option value="1" {{ ($processo['indicador_susp_exig'] ?? 0) ? 'selected' : '' }}>Sim</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Data da Decisão</label>
                    <input type="date" name="data_decisao" class="form-control"
                           value="{{ $processo['data_decisao'] ?? '' }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Depósito Judicial?</label>
                    <select name="indicador_deposito" class="form-select">
                        <option value="0" {{ !($processo['indicador_deposito'] ?? 0) ? 'selected' : '' }}>Não</option>
                        <option value="1" {{ ($processo['indicador_deposito'] ?? 0) ? 'selected' : '' }}>Sim</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição/Observação</label>
                    <textarea name="descricao" class="form-control" rows="2"
                              placeholder="Detalhes sobre o objeto do processo, decisão, etc.">{{ $processo['descricao'] ?? '' }}</textarea>
                </div>
            </div>

            <hr>
            <div class="d-flex justify-content-end gap-2">
                <a href="/processos" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>
