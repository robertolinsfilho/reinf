<div class="page-header">
    <h5><i class="bi bi-shield-exclamation text-warning me-2"></i>Validação XSD — Erros encontrados</h5>
    <a href="/gerar?competencia_id=<?= $competenciaId ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Um ou mais XMLs falharam na validação contra o XSD oficial.</strong>
    Se você transmitir mesmo assim, a Receita Federal provavelmente vai <strong>rejeitar</strong>.
    Revise os erros, corrija os dados e tente gerar novamente.
</div>

<?php foreach ($resultado['resultados'] as $r): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <span class="badge bg-primary me-1"><?= $r['evento'] ?></span>
            <span class="font-monospace small"><?= htmlspecialchars($r['nome']) ?></span>
        </span>
        <?php if ($r['valido']): ?>
            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Válido</span>
        <?php else: ?>
            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Inválido</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($r['aviso'])): ?>
        <div class="alert alert-info py-2 small mb-2">
            <i class="bi bi-info-circle me-1"></i> <?= htmlspecialchars($r['aviso']) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($r['erros'])): ?>
        <div class="bg-light p-3 rounded small">
            <strong class="text-danger">Erros de validação:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($r['erros'] as $erro): ?>
                <li class="font-monospace small"><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div class="text-success small"><i class="bi bi-check-circle me-1"></i> Sem erros.</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between gap-2 mt-3">
    <a href="/gerar?competencia_id=<?= $competenciaId ?>" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise me-1"></i> Corrigir e gerar novamente
    </a>

    <form action="/gerar/xml" method="POST" class="d-inline">
    <?= $csrfField ?>
        <input type="hidden" name="competencia_id" value="<?= $pendente['competencia_id'] ?>">
        <input type="hidden" name="ind_retif" value="<?= $pendente['ind_retif'] ?>">
        <input type="hidden" name="nr_recibo_original" value="<?= htmlspecialchars($pendente['nr_recibo_original'] ?? '') ?>">
        <?php if ($pendente['assinar']): ?><input type="hidden" name="assinar" value="1"><?php endif; ?>
        <?php foreach ($pendente['eventos'] as $ev): ?>
        <input type="hidden" name="eventos[]" value="<?= $ev ?>">
        <?php endforeach; ?>
        <input type="hidden" name="forcar" value="1">
        <button type="submit" class="btn btn-outline-danger"
                onclick="return confirm('Tem certeza? Os XMLs estão inválidos e podem ser rejeitados pela RFB.')">
            <i class="bi bi-exclamation-triangle me-1"></i> Forçar geração (ignorar validação)
        </button>
    </form>
</div>