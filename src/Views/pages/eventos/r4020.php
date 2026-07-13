<?php
$flash = $flash ?? null;
$e = $editando ?? null;
$isEdit = !empty($e);
$fmtMoney = static fn($v) => $v !== null && $v !== '' ? number_format((float) $v, 2, ',', '.') : '';
?>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= $isEdit ? 'Editar Pagamento PJ' : 'Adicionar Pagamento PJ (Formato Oficial RFB)' ?></span>
                <?php if ($isEdit): ?>
                <a href="/eventos/r4020?competencia_id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm py-0">Cancelar</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <form action="/eventos/r4020/salvar" method="POST">
    <?= $csrfField ?>
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">CNPJ Contribuinte</label>
                            <input type="text" name="cnpj_contribuinte" class="form-control form-control-sm font-monospace" data-mask="cnpj"
                                   value="<?= htmlspecialchars($e['cnpj_contribuinte'] ?? '') ?>"
                                   placeholder="Auto (preenchido pela competência)">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">CNPJ Beneficiário *</label>
                            <input type="text" name="cnpj_beneficiario" class="form-control form-control-sm font-monospace" data-mask="cnpj" required
                                   value="<?= htmlspecialchars($e['cnpj_beneficiario'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Razão Social do Beneficiário</label>
                        <input type="text" name="razao_social_beneficiario" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($e['razao_social_beneficiario'] ?? '') ?>">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">Nº NFS</label>
                            <input type="text" name="num_nfs" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($e['num_nfs'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Período Apuração</label>
                            <input type="date" name="periodo_apuracao" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($e['periodo_apuracao'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Data Fato Gerador *</label>
                            <input type="date" name="data_pagamento" class="form-control form-control-sm" required
                                   value="<?= htmlspecialchars($e['data_pagamento'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label small">Natureza do Rendimento (Tabela 01) *</label>
                            <?php $codSel = $e['cod_tipo_servico'] ?? $e['natureza_rendimento'] ?? ''; ?>
                            <select name="cod_tipo_servico" class="form-select form-select-sm" required>
                                <option value="">Selecione...</option>
                                <?php foreach (($naturezas ?? []) as $grupo => $items): ?>
                                <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                    <?php foreach ($items as $n): ?>
                                    <option value="<?= $n['codigo'] ?>" <?= (string) $codSel === (string) $n['codigo'] ? 'selected' : '' ?>>
                                        <?= $n['codigo'] ?> – <?= htmlspecialchars(mb_strimwidth($n['descricao'], 0, 70, '…')) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Cód. País (exterior)</label>
                            <input type="text" name="cod_pais" class="form-control form-control-sm" maxlength="3" placeholder="Ex: 249"
                                   value="<?= htmlspecialchars($e['cod_pais'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Valor Bruto *</label>
                            <input type="text" name="valor_bruto" class="form-control form-control-sm text-end" required placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_bruto'] ?? null) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Base IR</label>
                            <input type="text" name="valor_base_ir" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_base_ir'] ?? null) ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">IRRF</label>
                            <input type="text" name="valor_ir" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_ir'] ?? null) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">CSRF Agregado</label>
                            <input type="text" name="vl_csrf_agregado" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['vl_csrf_agregado'] ?? null) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">Base Agregado</label>
                            <input type="text" name="valor_base_agreg" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_base_agreg'] ?? null) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Base CSLL</label>
                            <input type="text" name="valor_base_csll" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_base_csll'] ?? null) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">CSLL</label>
                            <input type="text" name="valor_csll" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_csll'] ?? null) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">Base PIS</label>
                            <input type="text" name="valor_base_pis" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_base_pis'] ?? null) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">PIS</label>
                            <input type="text" name="valor_pis" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_pis'] ?? null) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Base COFINS</label>
                            <input type="text" name="valor_base_cofins" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_base_cofins'] ?? null) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">COFINS</label>
                            <input type="text" name="valor_cofins" class="form-control form-control-sm text-end" placeholder="0,00"
                                   value="<?= $fmtMoney($e['valor_cofins'] ?? null) ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Identificador Adicional (ideEvtAdic)</label>
                        <input type="text" name="identificador_adicional" class="form-control form-control-sm" maxlength="8" placeholder="Até 8 caracteres"
                               value="<?= htmlspecialchars($e['identificador_adicional'] ?? '') ?>">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">FCI / SCP</label>
                            <select name="indicador_fci_scp" id="ind-fci" class="form-select form-select-sm"
                                    onchange="document.getElementById('bloco-fci').style.display=this.value?'flex':'none'">
                                <option value="">— Não se aplica —</option>
                                <option value="1" <?= (string) ($e['indicador_fci_scp'] ?? '') === '1' ? 'selected' : '' ?>>1 – FCI</option>
                                <option value="2" <?= (string) ($e['indicador_fci_scp'] ?? '') === '2' ? 'selected' : '' ?>>2 – SCP</option>
                            </select>
                        </div>
                        <div id="bloco-fci" class="col-8 row g-2" style="display:<?= !empty($e['indicador_fci_scp']) ? 'flex' : 'none' ?>">
                            <div class="col-7">
                                <label class="form-label small">CNPJ FCI/SCP</label>
                                <input type="text" name="cnpj_fci_scp" class="form-control form-control-sm font-monospace" data-mask="cnpj"
                                       value="<?= htmlspecialchars($e['cnpj_fci_scp'] ?? '') ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label small">% SCP</label>
                                <input type="text" name="percentual_scp" class="form-control form-control-sm text-end" placeholder="0,00"
                                       value="<?= htmlspecialchars($e['percentual_scp'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-3">
                            <div class="form-check pt-4">
                                <input class="form-check-input" type="checkbox" name="indicador_judicial" value="1" id="chk-jud"
                                       <?= !empty($e['indicador_judicial']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="chk-jud">Judicial</label>
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label small">Nº Processo</label>
                            <input type="text" name="numero_processo" class="form-control form-control-sm font-monospace"
                                   value="<?= htmlspecialchars($e['numero_processo'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Origem recurso</label>
                            <select name="indicador_origem_recurso" id="ind-orig" class="form-select form-select-sm"
                                    onchange="document.getElementById('bloco-cnpj-orig').style.display=this.value==='2'?'block':'none'">
                                <option value="">—</option>
                                <option value="1" <?= (string) ($e['indicador_origem_recurso'] ?? '') === '1' ? 'selected' : '' ?>>1 – Próprio</option>
                                <option value="2" <?= (string) ($e['indicador_origem_recurso'] ?? '') === '2' ? 'selected' : '' ?>>2 – Terceiros</option>
                            </select>
                        </div>
                    </div>
                    <div id="bloco-cnpj-orig" class="mb-2" style="display:<?= (string) ($e['indicador_origem_recurso'] ?? '') === '2' ? 'block' : 'none' ?>">
                        <label class="form-label small">CNPJ Origem do Recurso *</label>
                        <input type="text" name="cnpj_origem_recurso" class="form-control form-control-sm font-monospace" data-mask="cnpj"
                               value="<?= htmlspecialchars($e['cnpj_origem_recurso'] ?? '') ?>"
                               placeholder="Obrigatório se origem = terceiros">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Observações</label>
                        <textarea name="observacoes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($e['observacoes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-sm">
                        <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?> me-1"></i>
                        <?= $isEdit ? 'Salvar Alterações' : 'Adicionar Pagamento' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Registros <span class="badge bg-secondary ms-1"><?= number_format($total ?? count($registros), 0, ',', '.') ?></span></span>
                <?php if (($totalPages ?? 1) > 1): ?>
                <div class="btn-group btn-group-sm">
                    <?php if (($page ?? 1) > 1): ?>
                    <a href="?competencia_id=<?= $competencia['id'] ?>&page=<?= $page - 1 ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    <span class="btn btn-outline-secondary disabled">
                        Página <?= $page ?? 1 ?> de <?= number_format($totalPages, 0, ',', '.') ?>
                    </span>
                    <?php if (($page ?? 1) < ($totalPages ?? 1)): ?>
                    <a href="?competencia_id=<?= $competencia['id'] ?>&page=<?= $page + 1 ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>CNPJ Benef.</th>
                            <th>Nat.</th>
                            <th>Data FG</th>
                            <th class="text-end">Bruto</th>
                            <th class="text-end">IR</th>
                            <th class="text-end">CSLL</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Nenhum registro. Adicione pagamentos PJ ou importe via Excel.
                            </td>
                        </tr>
                        <?php else: foreach ($registros as $r): ?>
                        <tr class="<?= $isEdit && (int) $e['id'] === (int) $r['id'] ? 'table-warning' : '' ?>">
                            <td class="font-monospace small"><?= htmlspecialchars($r['cnpj_beneficiario']) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($r['cod_tipo_servico'] ?? $r['natureza_rendimento'] ?? '') ?></td>
                            <td class="small"><?= !empty($r['data_pagamento']) ? date('d/m/Y', strtotime($r['data_pagamento'])) : '' ?></td>
                            <td class="text-end small">R$ <?= number_format((float) $r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format((float) $r['valor_ir'], 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format((float) ($r['valor_csll'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-nowrap">
                                <a href="/eventos/r4020?competencia_id=<?= (int) $competencia['id'] ?>&id=<?= (int) $r['id'] ?>"
                                   class="btn btn-outline-primary btn-sm py-0 px-1" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="/eventos/r4020/excluir" method="POST" class="d-inline" onsubmit="return confirm('Excluir?')">
    <?= $csrfField ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="competencia_id" value="<?= (int) $competencia['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Excluir"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($registros)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end small">Total página:</td>
                            <td class="text-end small">R$ <?= number_format(array_sum(array_column($registros, 'valor_bruto')), 2, ',', '.') ?></td>
                            <td class="text-end small text-danger">R$ <?= number_format(array_sum(array_column($registros, 'valor_ir')), 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format(array_sum(array_column($registros, 'valor_csll')), 2, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>
