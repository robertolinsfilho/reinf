<?php if (!empty($flash)): ?>
<div class="alert alert-<?= ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-file-earmark-excel me-2"></i>Importar Planilha Excel</h5>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Upload de Planilha</div>
            <div class="card-body p-4">

                <?php if (empty($contribuintes)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-building display-6 d-block mb-2"></i>
                    <p>Cadastre um contribuinte antes de importar.</p>
                    <a href="/contribuintes" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus me-1"></i> Contribuintes
                    </a>
                </div>
                <?php else: ?>

                <form action="/importar/processar" method="POST" enctype="multipart/form-data" id="form-importar">
    <?= $csrfField ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Modo de importação *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" id="modo-manual" value="manual" checked
                                   onchange="toggleModoImportacao()">
                            <label class="form-check-label" for="modo-manual">
                                <strong>Competência escolhida</strong> — importa tudo na competência selecionada
                            </label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="radio" name="modo" id="modo-auto" value="auto"
                                   onchange="toggleModoImportacao()">
                            <label class="form-check-label" for="modo-auto">
                                <strong>Por data da planilha</strong> — cria competências automaticamente
                                (hoje: <strong>R-2010</strong>, <strong>R-2055</strong> e <strong>R-4020</strong>)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3" id="bloco-manual">
                        <label class="form-label fw-bold">Competência *</label>
                        <?php if (empty($competencias)): ?>
                        <div class="alert alert-warning py-2 small mb-0">
                            Nenhuma competência. Crie uma ou use o modo automático (R-2010 / R-2055 / R-4020).
                            <a href="/competencias/nova">Criar competência</a>
                        </div>
                        <?php else: ?>
                        <select name="competencia_id" id="competencia_id" class="form-select">
                            <option value="">Selecione a competência...</option>
                            <?php foreach ($competencias as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['razao_social']) ?> — <?= $c['periodo'] ?>
                                (<?= ucfirst($c['status']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3 d-none" id="bloco-auto">
                        <label class="form-label fw-bold">Contribuinte <span id="lbl-contrib-obrigatorio" class="text-danger d-none">*</span></label>
                        <select name="contribuinte_id" id="contribuinte_id" class="form-select">
                            <option value="">— Selecione —</option>
                            <?php foreach ($contribuintes as $ct): ?>
                            <option value="<?= (int) $ct['id'] ?>">
                                <?= htmlspecialchars($ct['razao_social']) ?> — <?= htmlspecialchars($ct['cnpj']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="hint-auto">
                            <span id="hint-r2010" class="d-none">
                                R-2010: opcional se a planilha tiver <strong>CNPJ EMPRESA</strong> (modelo oficial).
                                Período pela data de emissão (col. H no modelo oficial, col. E no layout simples).
                            </span>
                            <span id="hint-r2055" class="d-none">
                                R-2055: opcional se a coluna A tiver CNPJ cadastrado. Período pela
                                <strong>coluna D (MM/AAAA ou data)</strong>.
                            </span>
                            <span id="hint-r4020" class="d-none">
                                R-4020: opcional se a coluna A tiver CNPJ cadastrado. Período pela
                                <strong>coluna E (Data Fato Gerador)</strong>; se vazia, coluna D.
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Evento *</label>
                        <select name="evento" id="evento" class="form-select" required onchange="toggleModoImportacao()">
                            <option value="">Selecione o evento...</option>
                            <option value="R2010">R-2010 – Retenções INSS (Serv. Tomados)</option>
                            <option value="R2020">R-2020 – Retenções INSS (Serv. Prestados)</option>
                            <option value="R2055">R-2055 – Aquisição Produção Rural</option>
                            <option value="R2060">R-2060 – CPRB</option>
                            <option value="R4010">R-4010 – Pagamentos PF (IRRF)</option>
                            <option value="R4020">R-4020 – Pagamentos PJ (IRRF/CSRF)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Arquivo Excel *</label>
                      <input type="file" name="arquivo" class="form-control" accept=".xlsx,.xlsm,.xls" required>
                      <div class="form-text">Formatos aceitos: .xlsx, .xlsm, .xls (máximo 50MB)</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-1"></i> Importar Planilha
                    </button>
                </form>

                <script>
                function toggleModoImportacao() {
                    const auto = document.getElementById('modo-auto').checked;
                    const evt = document.getElementById('evento').value;
                    const eventosAuto = ['R2010', 'R2055', 'R4020'];

                    if (auto && evt && !eventosAuto.includes(evt)) {
                        document.getElementById('modo-auto').checked = false;
                        document.getElementById('modo-manual').checked = true;
                        alert('Modo automático disponível por enquanto para R-2010, R-2055 e R-4020.');
                    }

                    const isAuto = document.getElementById('modo-auto').checked;
                    document.getElementById('bloco-manual').classList.toggle('d-none', isAuto);
                    document.getElementById('bloco-auto').classList.toggle('d-none', !isAuto);

                    const comp = document.getElementById('competencia_id');
                    if (comp) comp.required = !isAuto;

                    const contrib = document.getElementById('contribuinte_id');
                    const evtAtual = document.getElementById('evento').value;
                    if (contrib) contrib.required = false;
                    document.getElementById('lbl-contrib-obrigatorio').classList.add('d-none');
                    document.getElementById('hint-r2010').classList.toggle('d-none', !(isAuto && evtAtual === 'R2010'));
                    document.getElementById('hint-r2055').classList.toggle('d-none', !(isAuto && evtAtual === 'R2055'));
                    document.getElementById('hint-r4020').classList.toggle('d-none', !(isAuto && evtAtual === 'R4020'));
                }
                </script>

                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-question-circle me-2"></i>Formato das Planilhas</div>
            <div class="card-body small">
                <p class="text-muted mb-2">A primeira linha deve conter o cabeçalho. As colunas esperadas por evento:</p>

                <div class="mb-2">
                    <strong class="text-primary">R-2055 (modelo oficial):</strong>
                    A=CNPJ Empresa, B=Adquirente (CNPJ/CAEPF), C=Produtor (CNPJ/CPF),
                    D=Período (MM/AAAA ou data), E=indOpcCP (S ou vazio), F=indAquis,
                    G=Valor Bruto, H=CP Descontada, I=RAT/GILRAT, J=SENAR —
                    aba “R2055 - Aquisição Prod Rural”
                </div>
                <div class="mb-2">
                    <strong class="text-primary">R-2010 (modelo oficial .xlsm):</strong>
                    A=CNPJ Empresa, B=CNO, C=Obra, D=CNPJ Prestador, E=CPRB, F=Série,
                    G=NFS, H=Data Emissão, I=Valor Bruto, J=Obs, K=Tipo Serviço (Tab.6),
                    L=Base Cálculo, M=Retenção — aba “R2010 - Serviços tomados”
                </div>
                <div class="mb-2">
                    <strong class="text-primary">R-2010 (layout simples):</strong>
                    A=CNPJ Prestador, B=Razão Social, C=Tipo Insc, D=Nº Doc, E=Data, F=Valor Bruto,
                    G=Retenção, H=SENAR, I=Base Retenção (opc.), J=Tipo Serviço Tab.6 (opc.),
                    K=indCPRB 0/1 (opc.), L=Série (opc.)
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
                    <strong class="text-primary">R-4020 (formato oficial RFB):</strong>
                    A=CNPJ Contribuinte, B=CNPJ Prestador, C=Nº NFS, D=Período Apuração,
                    E=Data Fato Gerador, F=Valor Bruto, G=Cód Tipo Serviço (Tab 01), H=Cód País,
                    I=Base Cálculo, J=IRRF, K=CSRF, L=CSLL, M=PIS, N=COFINS,
                    O=Identificador, P-R=FCI/SCP, S=Judicial, T=Nº Processo, U=Origem, V=Observações
                </div>
            </div>
        </div>
    </div>

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
