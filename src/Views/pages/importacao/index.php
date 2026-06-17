<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-file-earmark-excel me-2"></i>Importar Planilha Excel</h5>
</div>

<div class="row g-3">
    <!-- Formulário de importação -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Importar dados de Planilha</div>
            <div class="card-body p-4">
                <form action="/importar/processar" method="POST" enctype="multipart/form-data">

                    <div class="mb-3">
                        <label class="form-label">Contribuinte</label>
                        <select name="contribuinte_id" id="sel-contrib" class="form-select" required>
                            <option value="">Selecione o contribuinte...</option>
                            <?php foreach ($contribuintes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razao_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Competência (Período)</label>
                        <select name="competencia_id" class="form-select" required id="sel-comp">
                            <option value="">Selecione a competência...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Evento REINF</label>
                        <select name="evento" class="form-select" required>
                            <option value="">Selecione o evento...</option>
                            <option value="R2010">R-2010 – Retenções INSS (Contratados)</option>
                            <option value="R2020">R-2020 – Retenções INSS (Contratantes)</option>
                            <option value="R2050">R-2050 – Comercialização Produção Rural PJ</option>
                            <option value="R2055">R-2055 – Aquisição Produção Rural</option>
                            <option value="R2060">R-2060 – CPRB</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Arquivo Excel (.xlsx / .xls)</label>
                        <input type="file" name="arquivo" class="form-control" accept=".xlsx,.xls" required>
                        <div class="form-text">Tamanho máximo: 50 MB</div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-upload me-1"></i> Processar Importação
                    </button>
                </form>
            </div>
        </div>

        <!-- Instruções de layout -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-table me-1"></i> Layout das Planilhas</div>
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="accordionLayout">
                    <?php
                    $layouts = [
                        'R2010' => ['A'=>'CNPJ Prestador','B'=>'Razão Social','C'=>'Nº Documento','D'=>'Data Emissão (DD/MM/AAAA)','E'=>'Valor Bruto','F'=>'Valor Retenção','G'=>'Valor SENAR'],
                        'R2020' => ['A'=>'CNPJ Tomador','B'=>'Razão Social','C'=>'Nº Documento','D'=>'Data Emissão','E'=>'Valor Bruto','F'=>'Valor Retenção'],
                        'R2060' => ['A'=>'CNAE','B'=>'Receita Bruta','C'=>'Exclusões','D'=>'Alíquota (%)'],
                    ];
                    foreach ($layouts as $ev => $cols): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#lay-<?= $ev ?>">
                                <?= $ev ?>
                            </button>
                        </h2>
                        <div id="lay-<?= $ev ?>" class="accordion-collapse collapse" data-bs-parent="#accordionLayout">
                            <div class="accordion-body p-2">
                                <table class="table table-sm mb-0" style="font-size:.78rem">
                                    <thead><tr><th>Col.</th><th>Conteúdo</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($cols as $col => $desc): ?>
                                        <tr><td class="font-monospace fw-bold"><?= $col ?></td><td><?= $desc ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-muted p-2" style="font-size:.75rem">Linha 1 = cabeçalho (ignorada). Dados a partir da linha 2.</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Histórico de Importações</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Contribuinte</th><th>Período</th><th>Evento</th><th>Arquivo</th><th>Registros</th><th>Status</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historico)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma importação realizada.</td></tr>
                        <?php else: foreach ($historico as $h): ?>
                        <tr>
                            <td style="font-size:.82rem"><?= htmlspecialchars($h['razao_social']) ?></td>
                            <td class="font-monospace small"><?= $h['periodo'] ?></td>
                            <td><span class="badge bg-primary"><?= $h['evento'] ?></span></td>
                            <td class="small text-muted" title="<?= htmlspecialchars($h['arquivo_nome'] ?? '') ?>">
                                <?= htmlspecialchars(substr($h['arquivo_nome'] ?? '', 0, 20)) ?>...
                            </td>
                            <td>
                                <?php if ($h['status'] === 'sucesso'): ?>
                                <span class="text-success"><?= $h['registros_importados'] ?>/<?= $h['total_registros'] ?></span>
                                <?php else: ?>–<?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusBadge = ['processando'=>'warning','sucesso'=>'success','erro'=>'danger'];
                                $label = ['processando'=>'Processando','sucesso'=>'Sucesso','erro'=>'Erro'];
                                ?>
                                <span class="badge bg-<?= $statusBadge[$h['status']] ?>"><?= $label[$h['status']] ?></span>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar competências ao selecionar contribuinte
document.getElementById('sel-contrib').addEventListener('change', async function() {
    const cid = this.value;
    const sel = document.getElementById('sel-comp');
    sel.innerHTML = '<option value="">Carregando...</option>';
    if (!cid) { sel.innerHTML = '<option value="">Selecione a competência...</option>'; return; }

    // Buscar via API simples (GET param)
    const res = await fetch('/competencias?contribuinte_id=' + cid + '&format=json');
    // Fallback: recarregar página com filtro
    window.location = '/competencias?contribuinte_id=' + cid;
});
</script>
