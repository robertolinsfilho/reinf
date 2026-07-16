<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5>R-2055 – Aquisição de Produção Rural</h5>
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
                <form action="/eventos/r2055/salvar" method="POST">
                    <?= $csrfField ?>
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label">Tipo Adquirente</label>
                            <select name="tp_insc_adquirente" class="form-select form-select-sm">
                                <option value="1">1 – CNPJ</option>
                                <option value="3">3 – CAEPF</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Nº Adquirente *</label>
                            <input type="text" name="nr_insc_adquirente" class="form-control font-monospace" required
                                   value="<?= htmlspecialchars(preg_replace('/\D/', '', $competencia['cnpj'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label">Tipo Produtor</label>
                            <select name="tp_insc_produtor" class="form-select form-select-sm">
                                <option value="1">1 – CNPJ</option>
                                <option value="2">2 – CPF</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Nº Produtor *</label>
                            <input type="text" name="nr_insc_produtor" class="form-control font-monospace" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">indAquis</label>
                            <select name="ind_aquis" class="form-select form-select-sm">
                                <option value="">Auto (1 CPF / 3 CNPJ)</option>
                                <option value="1">1 – PF/segurado especial</option>
                                <option value="2">2 – PF/PAA</option>
                                <option value="3">3 – PJ/PAA</option>
                                <option value="4">4 – PF isenta</option>
                                <option value="5">5 – PF/PAA isenta</option>
                                <option value="6">6 – PJ/PAA isenta</option>
                                <option value="7">7 – PF exportação</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">indOpcCP</label>
                            <select name="ind_opc_cp" class="form-select form-select-sm">
                                <option value="">— Comercialização (omitir)</option>
                                <option value="S">S – Opção folha</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label">Valor Bruto *</label>
                            <input type="text" name="valor_bruto" class="form-control text-end" placeholder="0,00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">CP Descontada</label>
                            <input type="text" name="valor_cp_desc" class="form-control text-end" placeholder="0,00">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">RAT / GILRAT</label>
                            <input type="text" name="valor_rat_desc" class="form-control text-end" placeholder="0,00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">SENAR</label>
                            <input type="text" name="valor_senar_desc" class="form-control text-end" placeholder="0,00">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Registro
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
                            <th>Adquirente</th>
                            <th>Produtor</th>
                            <th>indAquis</th>
                            <th class="text-end">Bruto</th>
                            <th class="text-end">CP</th>
                            <th class="text-end">SENAR</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro. Adicione ou <a href="/importar">importe pelo Excel</a>.</td></tr>
                        <?php else: foreach ($registros as $r): ?>
                        <tr>
                            <td class="font-monospace small"><?= htmlspecialchars($r['nr_insc_adquirente']) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($r['nr_insc_produtor']) ?></td>
                            <td class="small"><?= htmlspecialchars($r['ind_aquis']) ?></td>
                            <td class="text-end small">R$ <?= number_format((float) $r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format((float) $r['valor_cp_desc'], 2, ',', '.') ?></td>
                            <td class="text-end small">R$ <?= number_format((float) $r['valor_senar_desc'], 2, ',', '.') ?></td>
                            <td>
                                <form action="/eventos/r2055/excluir" method="POST" onsubmit="return confirm('Apagar este registro localmente?')">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="competencia_id" value="<?= (int) $competencia['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Apagar local"><i class="bi bi-trash3"></i></button>
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
