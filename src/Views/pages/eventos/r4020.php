<?php $flash = $flash ?? null; ?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5>R-4020 – Pagamentos/Créditos a Beneficiário PJ</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center"><?= htmlspecialchars($competencia['razao_social']) ?> | <?= $competencia['periodo'] ?></span>
        <a href="/competencias/detalhe?id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Adicionar Pagamento PJ (Formato Oficial RFB)</div>
            <div class="card-body p-3">
                <form action="/eventos/r4020/salvar" method="POST">
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">

                    <!-- Identificação -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">CNPJ Contribuinte</label>
                            <input type="text" name="cnpj_contribuinte" class="form-control form-control-sm font-monospace" data-mask="cnpj" placeholder="Auto (preenchido pela competência)">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">CNPJ Beneficiário *</label>
                            <input type="text" name="cnpj_beneficiario" class="form-control form-control-sm font-monospace" data-mask="cnpj" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Razão Social do Beneficiário</label>
                        <input type="text" name="razao_social_beneficiario" class="form-control form-control-sm">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">Nº NFS</label>
                            <input type="text" name="num_nfs" class="form-control form-control-sm">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Período Apuração</label>
                            <input type="date" name="periodo_apuracao" class="form-control form-control-sm">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Data Fato Gerador *</label>
                            <input type="date" name="data_pagamento" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <!-- Natureza (Tabela 4020) -->
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small">Cód. Tipo Serviço (Tabela 4020) *</label>
                            <select name="cod_tipo_servico" class="form-select form-select-sm" required>
                                <option value="">Selecione...</option>
                                <?php foreach (($naturezas ?? []) as $grupo => $items): ?>
                                <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                    <?php foreach ($items as $n): ?>
                                    <option value="<?= $n['codigo'] ?>">
                                        <?= $n['codigo'] ?> – <?= htmlspecialchars(mb_strimwidth($n['descricao'], 0, 70, '…')) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Cód. País (se exterior)</label>
                            <input type="text" name="cod_pais" class="form-control form-control-sm" maxlength="3" placeholder="Ex: 105">
                        </div>
                    </div>

                    <!-- Valores -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Valor Bruto *</label>
                            <input type="text" name="valor_bruto" class="form-control form-control-sm text-end" required placeholder="0,00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Base Cálculo</label>
                            <input type="text" name="valor_base_ir" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-3">
                            <label class="form-label small">IRRF</label>
                            <input type="text" name="valor_ir" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">CSRF Agregado</label>
                            <input type="text" name="vl_csrf_agregado" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                        <div class="col-2">
                            <label class="form-label small">CSLL</label>
                            <input type="text" name="valor_csll" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                        <div class="col-2">
                            <label class="form-label small">PIS</label>
                            <input type="text" name="valor_pis" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                        <div class="col-2">
                            <label class="form-label small">COFINS</label>
                            <input type="text" name="valor_cofins" class="form-control form-control-sm text-end" placeholder="0,00">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Identificador Adicional</label>
                        <input type="text" name="identificador_adicional" class="form-control form-control-sm" placeholder="Ex: Educação, Saúde">
                    </div>

                    <!-- Judicial -->
                    <div class="row g-2 mb-2">
                        <div class="col-3">
                            <div class="form-check pt-4">
                                <input class="form-check-input" type="checkbox" name="indicador_judicial" value="1" id="chk-jud">
                                <label class="form-check-label small" for="chk-jud">Judicial</label>
                            </div>
                        </div>
                        <div class="col-9">
                            <label class="form-label small">Nº Processo (se judicial)</label>
                            <input type="text" name="numero_processo" class="form-control form-control-sm font-monospace">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Observações</label>
                        <textarea name="observacoes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-sm">
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
                            <th>CNPJ</th><th>Cód</th><th>Data</th>
                            <th class="text-end">Bruto</th><th class="text-end">IR</th>
                            <th class="text-end">CSLL</th><th class="text-end">PIS/COF</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro.</td></tr>
                        <?php else: foreach ($registros as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= $r['cnpj_beneficiario'] ?></td>
                            <td class="font-monospace small"><?= $r['cod_tipo_servico'] ?? $r['natureza_rendimento'] ?></td>
                            <td class="small"><?= $r['data_pagamento'] ? date('d/m/Y', strtotime($r['data_pagamento'])) : '' ?></td>
                            <td class="text-end small">R$ <?= number_format($r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format($r['valor_ir'], 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format($r['valor_csll'], 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format(($r['valor_pis'] ?? 0) + ($r['valor_cofins'] ?? 0), 2, ',', '.') ?></td>
                            <td>
                                <form action="/eventos/r4020/excluir" method="POST" onsubmit="return confirm('Excluir?')">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>