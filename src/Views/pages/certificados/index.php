<?php
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-shield-lock me-2"></i>Certificado Digital A1</h5>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <!-- Upload -->
        <div class="card">
            <div class="card-header">Upload de Certificado (PFX / P12)</div>
            <div class="card-body p-4">
                <form action="/certificados/upload" method="POST" enctype="multipart/form-data">
    <?= $csrfField ?>
                    <div class="mb-3">
                        <label class="form-label">Arquivo do Certificado *</label>
                        <input type="file" name="certificado" class="form-control" accept=".pfx,.p12" required>
                        <div class="form-text">Certificado digital tipo A1, formato PFX ou P12.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha do Certificado *</label>
                        <input type="password" name="senha" class="form-control" required placeholder="Senha do arquivo PFX">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-1"></i> Enviar Certificado
                    </button>
                </form>
            </div>
        </div>

        <!-- Status -->
        <?php if (!empty($certAtivo)): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Certificado Ativo</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:120px">Titular</td>
                        <td class="fw-bold"><?= htmlspecialchars($certAtivo['titular'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">CNPJ</td>
                        <td class="font-monospace"><?= $certAtivo['cnpj'] ?? '—' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Emissão</td>
                        <td><?= $certAtivo['emissao'] ?? '—' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Validade</td>
                        <td>
                            <?= $certAtivo['validade'] ?? '—' ?>
                            <?php if (!empty($certAtivo['expirado'])): ?>
                                <span class="badge bg-danger ms-1">Expirado</span>
                            <?php elseif (($certAtivo['dias_rest'] ?? 999) <= 30): ?>
                                <span class="badge bg-warning ms-1"><?= $certAtivo['dias_rest'] ?> dias</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-1"><?= $certAtivo['dias_rest'] ?> dias</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            <?php if ($certAtivo['valido'] ?? false): ?>
                                <i class="bi bi-check-circle-fill text-success"></i> Válido
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i> Inválido
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Nenhum certificado ativo.</strong>
            Faça o upload do seu certificado A1 para habilitar a assinatura e transmissão de eventos.
            Sem certificado, o sistema opera em <strong>modo simulação</strong>.
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Certificados Cadastrados</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Arquivo</th><th>Titular</th><th>CNPJ</th><th>Validade</th><th>Status</th><th>Importado</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificados)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-shield-x display-6 d-block mb-2"></i>
                            Nenhum certificado cadastrado.
                        </td></tr>
                        <?php else: foreach ($certificados as $c): ?>
                        <tr class="<?= $c['ativo'] ? 'table-success' : '' ?>">
                            <td class="small"><?= htmlspecialchars($c['nome_arquivo'] ?? '') ?></td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($c['titular'] ?? '—') ?></td>
                            <td class="font-monospace small"><?= $c['cnpj_certificado'] ?? '—' ?></td>
                            <td class="small"><?= $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : '—' ?></td>
                            <td>
                                <?php if ($c['ativo']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-question-circle me-2"></i>Sobre o Certificado Digital</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>O certificado digital A1 (arquivo PFX/P12) é necessário para <strong>assinar</strong> e <strong>transmitir</strong> eventos à Receita Federal.</li>
                    <li>Sem certificado, o sistema gera os XMLs normalmente, mas os envios operam em <strong>modo simulação</strong>.</li>
                    <li>O certificado deve estar no nome do contribuinte (CNPJ) que fará a transmissão.</li>
                    <li>Certificados A1 têm validade de 1 ano. O sistema alertará quando estiver próximo do vencimento.</li>
                    <li>A senha do certificado é usada apenas no momento do upload para validação — não é armazenada.</li>
                </ul>
            </div>
        </div>
    </div>
</div>