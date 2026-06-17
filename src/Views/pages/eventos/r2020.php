<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5>R-2020 – Retenções INSS Contratantes</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center"><?= htmlspecialchars($competencia['razao_social']) ?> | <?= $competencia['periodo'] ?></span>
        <a href="/competencias/detalhe?id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Adicionar Registro</div>
            <div class="card-body p-4">
                <form action="/eventos/r2020/salvar" method="POST">
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label">CNPJ / CPF do Tomador *</label>
                        <input type="text" name="cnpj_tomador" class="form-control font-monospace" data-mask="cnpj" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Razão Social</label>
                        <input type="text" name="razao_social_tomador" class="form-control">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">Nº Documento</label>
                            <input type="text" name="num_documento" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data Emissão</label>
                            <input type="date" name="data_emissao" class="form-control">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Valor Bruto *</label>
                            <input type="text" name="valor_bruto" class="form-control text-end" required placeholder="0,00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor Retenção</label>
                            <input type="text" name="valor_retencao" class="form-control text-end" placeholder="0,00">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Registros <span class="badge bg-secondary ms-1"><?= count($registros) ?></span></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>CNPJ/CPF</th><th>Tomador</th><th>Doc.</th><th class="text-end">Bruto</th><th class="text-end">Retenção</th></tr></thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sem registros.</td></tr>
                        <?php else: foreach ($registros as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= $r['cnpj_tomador'] ?></td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($r['razao_social_tomador'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($r['num_documento'] ?? '') ?></td>
                            <td class="text-end small">R$ <?= number_format($r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format($r['valor_retencao'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
