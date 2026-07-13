<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-calendar-plus me-2"></i>Nova Competência</h5>
    <a href="/competencias" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<div class="card" style="max-width:500px">
    <div class="card-body p-4">
        <form action="/competencias/salvar" method="POST">
    <?= $csrfField ?>
            <div class="mb-3">
                <label class="form-label">Contribuinte *</label>
                <select name="contribuinte_id" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($contribuintes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razao_social']) ?> – <?= $c['cnpj'] ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($contribuintes)): ?>
                <div class="form-text text-warning">
                    <a href="/contribuintes/novo">Cadastre um contribuinte primeiro</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="mb-4">
                <label class="form-label">Período de Apuração *</label>
                <input type="month" name="periodo" class="form-control" required
                       max="<?= date('Y-m') ?>">
                <div class="form-text">Mês/Ano de referência da EFD REINF</div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" <?= empty($contribuintes) ? 'disabled' : '' ?>>
                    <i class="bi bi-check-lg me-1"></i> Criar Competência
                </button>
                <a href="/competencias" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
