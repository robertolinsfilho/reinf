@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif
<div class="page-header">
    <h5><i class="bi bi-people me-2"></i>Usuários</h5>
    <a href="/usuarios/novo" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Novo Usuário</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Trial Expira</th><th>Status</th><th>Cadastro</th></tr></thead>
            <tbody>
                @foreach($usuarios as $u)
                <tr>
                    <td>{{ $u['nome'] }}</td>
                    <td>{{ $u['email'] }}</td>
                    <td><span class="badge bg-{{ $u['perfil']==='admin'?'danger':'primary' }}">{{ $u['perfil'] }}</span></td>
                    <td>{{ $u['trial_expira'] ? date('d/m/Y', strtotime($u['trial_expira'])) : '–' }}</td>
                    <td><span class="badge bg-{{ $u['ativo']?'success':'secondary' }}">{{ $u['ativo']?'Ativo':'Inativo' }}</span></td>
                    <td class="small text-muted">{{ date('d/m/Y', strtotime($u['created_at'])) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
