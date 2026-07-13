<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>
<div class="page-header">
    <h5><i class="bi bi-person-circle me-2"></i>Meu Perfil</h5>
</div>
<div class="card" style="max-width:420px">
    <div class="card-body p-4">
        <form action="/perfil/salvar" method="POST">
    <?= $csrfField ?>
            <div class="mb-3">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
            </div>
            <div class="mb-4">
                <label class="form-label">Nova Senha <span class="text-muted small">(deixe em branco para não alterar)</span></label>
                <input type="password" name="senha" class="form-control" minlength="6">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button>
        </form>
    </div>
</div>
