<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-file-earmark-code me-2"></i>Gerar Arquivo EFD REINF</h5>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Configurar Geração</div>
            <div class="card-body p-4">
                <form action="/gerar/xml" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Competência</label>
                        <select name="competencia_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($competencias as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['razao_social']) ?> – <?= $c['periodo'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Eventos a gerar</label>
                        <div class="d-grid gap-2">
                            <?php
                            $eventosList = [
                                'R1000' => 'R-1000 – Informações do Contribuinte',
                                'R2010' => 'R-2010 – Retenções INSS (Contratados)',
                                'R2020' => 'R-2020 – Retenções INSS (Contratantes)',
                                'R2050' => 'R-2050 – Comercialização Produção Rural',
                                'R2055' => 'R-2055 – Aquisição Produção Rural',
                                'R2060' => 'R-2060 – CPRB',
                                'R9000' => 'R-9000 – Exclusão de Evento',
                            ];
                            foreach ($eventosList as $cod => $desc): ?>
                            <div class="form-check form-check-lg border rounded p-3 ps-5" style="cursor:pointer">
                                <input class="form-check-input" type="checkbox" name="eventos[]" value="<?= $cod ?>" id="ev-<?= $cod ?>">
                                <label class="form-check-label w-100" for="ev-<?= $cod ?>" style="cursor:pointer">
                                    <span class="badge bg-primary me-1"><?= $cod ?></span>
                                    <span style="font-size:.85rem"><?= $desc ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-file-earmark-code me-1"></i> Gerar Arquivos XML
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-archive me-2"></i>Arquivos Gerados</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Contribuinte</th><th>Período</th><th>Arquivo</th><th>Tamanho</th><th>Gerado em</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($arquivos)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
                            Nenhum arquivo gerado ainda.
                        </td></tr>
                        <?php else: foreach ($arquivos as $a): ?>
                        <tr>
                            <td style="font-size:.82rem"><?= htmlspecialchars($a['razao_social']) ?></td>
                            <td class="font-monospace small"><?= $a['periodo'] ?></td>
                            <td class="font-monospace small text-primary"><?= htmlspecialchars($a['nome_arquivo']) ?></td>
                            <td class="small text-muted"><?= number_format($a['tamanho'] / 1024, 1) ?> KB</td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                            <td>
                                <a href="/download?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download"></i> Baixar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Informações sobre o ambiente -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Sobre os Arquivos Gerados</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>Os arquivos são gerados no formato XML conforme layout da EFD-REINF v2.x</li>
                    <li>Ambiente configurado como <strong>Homologação (tpAmb=2)</strong> – altere para produção antes de transmitir</li>
                    <li>A transmissão ao portal da Receita Federal requer certificado digital A1 ou A3</li>
                    <li>Após gerar, valide o arquivo no <strong>PVA SPED</strong> antes de transmitir</li>
                    <li>Os arquivos ficam armazenados por 30 dias</li>
                </ul>
            </div>
        </div>
    </div>
</div>
