@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif
<div class="page-header">
    <h5>Novo Usuário</h5>
    <a href="/usuarios" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>
<div class="card" style="max-width:480px">
    <div class="card-body p-4">
        <form action="/usuarios/salvar" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">E-mail *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha *</label>
                <input type="password" name="senha" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label">Perfil</label>
                <select name="perfil" class="form-select">
                    <option value="usuario">Usuário</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Trial – Expira em</label>
                <input type="date" name="trial_expira" class="form-control">
                <div class="form-text">Deixe em branco para acesso ilimitado</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Criar Usuário</button>
        </form>
    </div>
</div>
