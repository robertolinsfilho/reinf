<?php $flash = $flash ?? $this->getFlash ?? null; ?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5>R-4010 – Pagamentos/Créditos a Beneficiário PF (IRRF)</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center"><?= htmlspecialchars($competencia['razao_social']) ?> | <?= $competencia['periodo'] ?></span>
        <a href="/competencias/detalhe?id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Adicionar Pagamento PF</div>
            <div class="card-body p-4">
                <form action="/eventos/r4010/salvar" method="POST">
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label">CPF do Beneficiário *</label>
                        <input type="text" name="cpf_beneficiario" class="form-control font-monospace" data-mask="cpf" required placeholder="000.000.000-00">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nome do Beneficiário</label>
                        <input type="text" name="nome_beneficiario" class="form-control" placeholder="Nome completo">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">Natureza Rendimento *</label>
                            <<select name="natureza_rendimento" class="form-select form-select-sm" required>
                                <option value="">Selecione a natureza...</option>
                                <?php foreach (($naturezas ?? []) as $grupo => $items): ?>
                                <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                    <?php foreach ($items as $n): ?>
                                    <option value="<?= $n['codigo'] ?>">
                                        <?= $n['codigo'] ?> – <?= htmlspecialchars($n['descricao']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data Pagamento *</label>
                            <input type="date" name="data_pagamento" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">Valor Bruto *</label>
                            <input type="text" name="valor_bruto" class="form-control text-end" placeholder="0,00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Base IR</label>
                            <input type="text" name="valor_base_ir" class="form-control text-end" placeholder="0,00">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">IR Retido</label>
                            <input type="text" name="valor_ir" class="form-control text-end" placeholder="0,00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Deduções</label>
                            <input type="text" name="valor_deducao" class="form-control text-end" placeholder="0,00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <input type="text" name="descricao_pagamento" class="form-control" placeholder="Ex: Prestação de serviço ref. maio/2026">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Pagamento
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                Registros <span class="badge bg-secondary ms-1"><?= count($registros) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>CPF</th><th>Beneficiário</th><th>Nat.</th><th>Data</th>
                            <th class="text-end">Bruto</th><th class="text-end">IR</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro. Adicione pagamentos PF com retenção de IR.</td></tr>
                        <?php else: foreach ($registros as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= $r['cpf_beneficiario'] ?></td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($r['nome_beneficiario'] ?? '') ?></td>
                            <td class="font-monospace small"><?= $r['natureza_rendimento'] ?></td>
                            <td class="small"><?= $r['data_pagamento'] ? date('d/m/Y', strtotime($r['data_pagamento'])) : '' ?></td>
                            <td class="text-end small">R$ <?= number_format($r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format($r['valor_ir'], 2, ',', '.') ?></td>
                            <td>
                                <form action="/eventos/r4010/excluir" method="POST" onsubmit="return confirm('Excluir?')">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($registros)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end small">Total:</td>
                            <td class="text-end small">R$ <?= number_format(array_sum(array_column($registros, 'valor_bruto')), 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format(array_sum(array_column($registros, 'valor_ir')), 2, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>