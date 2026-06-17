<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-primary-custom">
            <div class="icon"><i class="bi bi-building"></i></div>
            <div>
                <div class="label">Contribuintes</div>
                <div class="value"><?= $totalContribuintes ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-success-custom">
            <div class="icon"><i class="bi bi-calendar3"></i></div>
            <div>
                <div class="label">Competências</div>
                <div class="value"><?= $totalCompetencias ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-info-custom">
            <div class="icon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="label">Transmitidos</div>
                <div class="value"><?= $totalTransmitidos ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Últimas competências -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Últimas Competências</span>
                <a href="/competencias/nova" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Nova
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Contribuinte</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimasCompetencias)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma competência criada ainda.</td></tr>
                        <?php else: foreach ($ultimasCompetencias as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-500" style="font-size:.88rem"><?= htmlspecialchars($c['razao_social']) ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?= $c['cnpj'] ?></div>
                            </td>
                            <td><?= $c['periodo'] ?></td>
                            <td>
                                <span class="badge badge-status-<?= $c['status'] ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/competencias/detalhe?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ações rápidas -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-lightning-charge me-2"></i>Ações Rápidas</div>
            <div class="card-body d-grid gap-2">
                <a href="/importar" class="btn btn-outline-primary text-start">
                    <i class="bi bi-file-earmark-excel me-2"></i> Importar Planilha Excel
                </a>
                <a href="/gerar" class="btn btn-outline-success text-start">
                    <i class="bi bi-file-earmark-code me-2"></i> Gerar Arquivo XML
                </a>
                <a href="/contribuintes/novo" class="btn btn-outline-secondary text-start">
                    <i class="bi bi-building-add me-2"></i> Novo Contribuinte
                </a>
                <a href="/competencias/nova" class="btn btn-outline-secondary text-start">
                    <i class="bi bi-calendar-plus me-2"></i> Nova Competência
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Eventos Suportados</div>
            <div class="card-body p-0">
                <?php
                $eventos = [
                    ['R-1000','Informações do Contribuinte','primary'],
                    ['R-2010','Retenções INSS – Contratados','success'],
                    ['R-2020','Retenções INSS – Contratantes','success'],
                    ['R-2050','Comercialização Produção Rural','info'],
                    ['R-2055','Aquisição Produção Rural','info'],
                    ['R-2060','CPRB – Contribuição Prev.','warning'],
                    ['R-9000','Exclusão de Eventos','danger'],
                ];
                foreach ($eventos as [$cod, $desc, $cor]): ?>
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <span class="badge bg-<?= $cor ?>" style="min-width:56px"><?= $cod ?></span>
                    <span style="font-size:.82rem"><?= $desc ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
