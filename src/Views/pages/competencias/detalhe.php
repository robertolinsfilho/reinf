<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-calendar3 me-2"></i>Competência <?= $competencia['periodo'] ?></h5>
    <a href="/competencias" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<!-- Info -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted">Contribuinte</small>
                <div class="fw-600"><?= htmlspecialchars($competencia['razao_social']) ?></div>
                <div class="font-monospace small text-muted"><?= $competencia['cnpj'] ?></div>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Período</small>
                <div class="fw-600"><?= $competencia['periodo'] ?></div>
            </div>
            <div class="col-md-2">
                <small class="text-muted">Status</small>
                <div><span class="badge badge-status-<?= $competencia['status'] ?>"><?= ucfirst($competencia['status']) ?></span></div>
            </div>
            <div class="col-md-2 d-flex align-items-center gap-2">
                <a href="/importar?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-upload me-1"></i> Importar
                </a>
                <a href="/gerar?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-file-code me-1"></i> Gerar XML
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Tabs de eventos -->
<ul class="nav nav-tabs mb-3" id="eventosTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab-r2010">
            R-2010 <span class="badge bg-secondary ms-1"><?= count($r2010) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r2020">
            R-2020 <span class="badge bg-secondary ms-1"><?= count($r2020) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-r2060">
            R-2060 <span class="badge bg-secondary ms-1"><?= count($r2060) ?></span>
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- R-2010 -->
    <div class="tab-pane active" id="tab-r2010">
        <div class="d-flex justify-content-between mb-2">
            <h6 class="text-muted">Retenções INSS – Serviços Tomados</h6>
            <a href="/eventos/r2010?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus"></i> Adicionar
            </a>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 table-sm">
                    <thead>
                        <tr><th>CNPJ Prestador</th><th>Razão Social</th><th>Documento</th><th class="text-end">Valor Bruto</th><th class="text-end">Retenção</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($r2010)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum registro</td></tr>
                        <?php else: foreach ($r2010 as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= $r['cnpj_prestador'] ?></td>
                            <td><?= htmlspecialchars($r['razao_social_prestador'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($r['num_documento'] ?? '') ?></td>
                            <td class="text-end">R$ <?= number_format($r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end text-danger">R$ <?= number_format($r['valor_retencao'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($r2010)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">Total:</td>
                            <td class="text-end">R$ <?= number_format(array_sum(array_column($r2010,'valor_bruto')), 2, ',', '.') ?></td>
                            <td class="text-end text-danger">R$ <?= number_format(array_sum(array_column($r2010,'valor_retencao')), 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- R-2020 -->
    <div class="tab-pane" id="tab-r2020">
        <div class="d-flex justify-content-between mb-2">
            <h6 class="text-muted">Retenções INSS – Serviços Prestados</h6>
            <a href="/eventos/r2020?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus"></i> Adicionar
            </a>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 table-sm">
                    <thead>
                        <tr><th>CNPJ Tomador</th><th>Razão Social</th><th>Documento</th><th class="text-end">Valor Bruto</th><th class="text-end">Retenção</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($r2020)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum registro</td></tr>
                        <?php else: foreach ($r2020 as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= $r['cnpj_tomador'] ?></td>
                            <td><?= htmlspecialchars($r['razao_social_tomador'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($r['num_documento'] ?? '') ?></td>
                            <td class="text-end">R$ <?= number_format($r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end text-danger">R$ <?= number_format($r['valor_retencao'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R-2060 -->
    <div class="tab-pane" id="tab-r2060">
        <div class="d-flex justify-content-between mb-2">
            <h6 class="text-muted">CPRB – Contribuição Previdenciária sobre Receita Bruta</h6>
            <a href="/eventos/r2060?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus"></i> Adicionar
            </a>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 table-sm">
                    <thead>
                        <tr><th>CNAE</th><th class="text-end">Rec. Bruta</th><th class="text-end">Exclusões</th><th class="text-end">Base Cálculo</th><th class="text-end">Alíq.</th><th class="text-end">Contribuição</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($r2060)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhum registro</td></tr>
                        <?php else: foreach ($r2060 as $r): ?>
                        <tr>
                            <td><?= $r['cnae'] ?></td>
                            <td class="text-end">R$ <?= number_format($r['valor_rec_bruta'], 2, ',', '.') ?></td>
                            <td class="text-end">R$ <?= number_format($r['valor_rec_bruta_excl'], 2, ',', '.') ?></td>
                            <td class="text-end">R$ <?= number_format($r['valor_base_calculo'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= $r['aliquota'] ?>%</td>
                            <td class="text-end text-danger fw-bold">R$ <?= number_format($r['valor_contribuicao'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
