@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-building me-2"></i>Contribuintes</h5>
    <a href="/contribuintes/novo" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Contribuinte
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>CNPJ / CPF</th>
                    <th>Razão Social</th>
                    <th>Nome Fantasia</th>
                    <th>Tipo</th>
                    <th>Cadastro</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @if(empty($contribuintes))
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-building display-6 d-block mb-2"></i>
                        Nenhum contribuinte cadastrado.<br>
                        <a href="/contribuintes/novo" class="btn btn-sm btn-primary mt-2">Cadastrar agora</a>
                    </td>
                </tr>
                @else
                @foreach($contribuintes as $c)
                <tr>
                    <td class="font-monospace small">{{ $c['cnpj'] }}</td>
                    <td>{{ $c['razao_social'] }}</td>
                    <td class="text-muted">{{ $c['nome_fantasia'] ?? '-' }}</td>
                    <td>
                        @php $tipos = ['1'=>'CNPJ','2'=>'CPF','3'=>'CAEPF','4'=>'CNO']; @endphp
                        <span class="badge bg-secondary">{{ $tipos[$c['tipo_contribuinte']] ?? $c['tipo_contribuinte'] }}</span>
                    </td>
                    <td class="small text-muted">{{ date('d/m/Y', strtotime($c['created_at'])) }}</td>
                    <td class="text-end">
                        <a href="/competencias?contribuinte_id={{ $c['id'] }}" class="btn btn-sm btn-outline-primary" title="Ver competências">
                            <i class="bi bi-calendar3"></i>
                        </a>
                        <a href="/contribuintes/editar?id={{ $c['id'] }}" class="btn btn-sm btn-outline-secondary ms-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="/contribuintes/excluir" method="POST" class="d-inline ms-1"
                              onsubmit="return confirm('Excluir este contribuinte e todos os seus dados?')">
                            @csrf
                            <input type="hidden" name="id" value="{{ (int) $c['id'] }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>
