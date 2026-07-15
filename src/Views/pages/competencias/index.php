<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-calendar3 me-2"></i>Competências</h5>
    <a href="/competencias/nova" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nova Competência</a>
</div>

<?php if (!empty($contribuintes)): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2 align-items-center" method="GET" action="/competencias">
            <label class="form-label mb-0 text-nowrap">Filtrar por:</label>
            <select name="contribuinte_id" class="form-select form-select-sm" style="max-width:300px">
                <option value="">Todos os contribuintes</option>
                <?php foreach ($contribuintes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $contribuinteId == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['razao_social']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary">Filtrar</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Contribuinte</th>
                    <th>Período</th>
                    <th>R-2010</th>
                    <th>R-2020</th>
                    <th>R-2055</th>
                    <th>R-2060</th>
                    <th>R-4010</th>
                    <th>R-4020</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($competencias)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x display-6 d-block mb-2"></i>
                        Nenhuma competência encontrada.<br>
                        <a href="/competencias/nova" class="btn btn-sm btn-primary mt-2">Criar agora</a>
                    </td>
                </tr>
                <?php else: foreach ($competencias as $c): ?>
                <tr>
                    <td>
                        <div style="font-size:.88rem;font-weight:500"><?= htmlspecialchars($c['razao_social']) ?></div>
                        <div class="text-muted font-monospace" style="font-size:.75rem"><?= $c['cnpj'] ?></div>
                    </td>
                    <td class="font-monospace"><?= $c['periodo'] ?></td>
                    <td><?= ($c['total_r2010'] ?? 0) > 0 ? "<span class='badge bg-success'>{$c['total_r2010']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td><?= ($c['total_r2020'] ?? 0) > 0 ? "<span class='badge bg-success'>{$c['total_r2020']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td><?= ($c['total_r2055'] ?? 0) > 0 ? "<span class='badge bg-info'>{$c['total_r2055']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td><?= ($c['total_r2060'] ?? 0) > 0 ? "<span class='badge bg-success'>{$c['total_r2060']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td><?= ($c['total_r4010'] ?? 0) > 0 ? "<span class='badge bg-success'>{$c['total_r4010']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td><?= ($c['total_r4020'] ?? 0) > 0 ? "<span class='badge bg-primary'>{$c['total_r4020']}</span>" : '<span class="text-muted">–</span>' ?></td>
                    <td>
                        <span class="badge badge-status-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
                    </td>
                    <td class="text-end">
                        <a href="/competencias/detalhe?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Detalhe">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="/importar?competencia_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success ms-1" title="Importar Excel">
                            <i class="bi bi-file-earmark-excel"></i>
                        </a>
                        <a href="/gerar?competencia_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info ms-1" title="Gerar XML">
                            <i class="bi bi-file-code"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
