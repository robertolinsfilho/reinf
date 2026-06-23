<?php if (!empty($flash)): ?>
<div class="alert alert-<?= ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-file-earmark-excel me-2"></i>Importar Planilha Excel</h5>
</div>

<div class="row g-3">
    <!-- Formulário de importação -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Upload de Planilha</div>
            <div class="card-body p-4">

                <?php if (empty($competencias)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x display-6 d-block mb-2"></i>
                    <p>Nenhuma competência criada.</p>
                    <a href="/competencias/nova" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus me-1"></i> Criar Competência
                    </a>
                </div>
                <?php else: ?>

                <form action="/importar/processar" method="POST" enctype="multipart/form-data">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Competência *</label>
                        <select name="competencia_id" class="form-select" required>
                            <option value="">Selecione a competência...</option>
                            <?php foreach ($competencias as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['razao_social']) ?> — <?= $c['periodo'] ?>
                                (<?= ucfirst($c['status']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Evento *</label>
                        <select name="evento" class="form-select" required>
                            <option value="">Selecione o evento...</option>
                            <option value="R2010">R-2010 – Retenções INSS (Serv. Tomados)</option>
                            <option value="R2020">R-2020 – Retenções INSS (Serv. Prestados)</option>
                            <option value="R2060">R-2060 – CPRB</option>
                            <option value="R4010">R-4010 – Pagamentos PF (IRRF)</option>
                            <option value="R4020">R-4020 – Pagamentos PJ (IRRF/CSRF)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Arquivo Excel *</label>
                        <input type="file" name="arquivo" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">Formatos aceitos: .xlsx, .xls (máximo 50MB)</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-1"></i> Importar Planilha
                    </button>
                </form>

                <?php endif; ?>
            </div>
        </div>

        <!-- Formato esperado -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-question-circle me-2"></i>Formato das Planilhas</div>
            <div class="card-body small">
                <p class="text-muted mb-2">A primeira linha deve conter o cabeçalho. As colunas esperadas por evento:</p>

                <div class="mb-2">
                    <strong class="text-primary">R-2010:</strong>
                    A=CNPJ Prestador, B=Razão Social, C=Tipo Insc, D=Nº Doc, E=Data, F=Valor Bruto, G=Retenção, H=SENAR
                </div>
                <div class="mb-2">
                    <strong class="text-primary">R-2020:</strong>
                    A=CNPJ Tomador, B=Razão Social, C=Tipo Insc, D=Nº Doc, E=Data, F=Valor Bruto, G=Retenção
                </div>
                <div class="mb-2">
                    <strong class="text-primary">R-2060:</strong>
                    A=CNAE, B=Receita Bruta, C=Exclusões, D=Alíquota
                </div>
                <div class="mb-2">
                    <strong class="text-primary">R-4010:</strong>
                    A=CPF, B=Nome, C=Natureza, D=Data Pagto, E=Bruto, F=Base IR, G=IR, H=Dedução
                </div>
                <div class="mb-0">
                    <strong class="text-primary">R-4020:</strong>
                    A=CNPJ, B=Razão Social, C=Natureza, D=Data Pagto, E=Bruto, F=Base IR, G=IR, H=CSLL, I=COFINS, J=PIS
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Histórico de Importações
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Contribuinte</th>
                            <th>Evento</th>
                            <th>Arquivo</th>
                            <th>Resultado</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historico)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                Nenhuma importação realizada ainda.
                            </td>
                        </tr>
                        <?php else: foreach ($historico as $h): ?>
                        <tr>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($h['razao_social'] ?? '—') ?></td>
                            <td><span class="badge bg-primary"><?= $h['evento'] ?? '—' ?></span></td>
                            <td class="small" title="<?= htmlspecialchars($h['arquivo_nome'] ?? '') ?>">
                                <?= htmlspecialchars(mb_strimwidth($h['arquivo_nome'] ?? '—', 0, 25, '…')) ?>
                            </td>
                            <td class="small">
                                <?= $h['registros_importados'] ?? 0 ?> / <?= $h['total_registros'] ?? 0 ?>
                            </td>
                            <td>
                                <?php if ($h['status'] === 'sucesso'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                                <?php elseif ($h['status'] === 'erro'): ?>
                                    <span class="badge bg-danger" title="<?= htmlspecialchars($h['log_erros'] ?? '') ?>"><i class="bi bi-x-lg"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="bi bi-hourglass-split"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>