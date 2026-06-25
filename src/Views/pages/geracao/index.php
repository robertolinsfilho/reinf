<?php
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-file-earmark-code me-2"></i>Gerar XML EFD-REINF</h5>
    <div class="d-flex gap-2">
        <span class="badge bg-secondary align-self-center"><?= htmlspecialchars($competencia['razao_social']) ?> | <?= $competencia['periodo'] ?></span>
        <a href="/competencias/detalhe?id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Selecionar Eventos</div>
            <div class="card-body p-4">
                <form action="/gerar/xml" method="POST">
                    <input type="hidden" name="competencia_id" value="<?= $competencia['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Eventos a gerar</label>
                        <div class="d-grid gap-2">
                            <?php
                            $eventosList = [
                                'R1000' => ['R-1000 – Informações do Contribuinte', 'bi-building', true],
                                'R1070' => ['R-1070 – Processos Admin./Judiciais', 'bi-folder2-open', true],
                                'R2010' => ['R-2010 – Retenções INSS (Tomados)', 'bi-arrow-down-left-circle', $eventosDisponiveis['R2010'] ?? false],
                                'R2020' => ['R-2020 – Retenções INSS (Prestados)', 'bi-arrow-up-right-circle', $eventosDisponiveis['R2020'] ?? false],
                                'R2060' => ['R-2060 – CPRB', 'bi-calculator', $eventosDisponiveis['R2060'] ?? false],
                                'R4010' => ['R-4010 – Pagamentos PF (IRRF)', 'bi-person', $eventosDisponiveis['R4010'] ?? false],
                                'R4020' => ['R-4020 – Pagamentos PJ (IRRF/CSRF)', 'bi-building', $eventosDisponiveis['R4020'] ?? false],
                                'R2099' => ['R-2099 – Fechamento Série R-2000', 'bi-lock', true],
                                'R4099' => ['R-4099 – Fechamento Série R-4000', 'bi-lock-fill', true],
                                'R9000' => ['R-9000 – Exclusão de Evento', 'bi-trash', true],
                            ];
                            foreach ($eventosList as $cod => [$desc, $icon, $temDados]):
                                $disabled = !$temDados && !in_array($cod, ['R1000','R1070','R2099','R4099','R9000']);
                            ?>
                            <div class="form-check border rounded p-3 ps-5 <?= $disabled ? 'opacity-50' : '' ?>" style="cursor:<?= $disabled ? 'not-allowed' : 'pointer' ?>">
                                <input class="form-check-input" type="checkbox" name="eventos[]"
                                       value="<?= $cod ?>" id="ev-<?= $cod ?>"
                                       <?= $disabled ? 'disabled' : '' ?>
                                       <?= ($temDados && !in_array($cod, ['R1000','R2099','R4099','R9000'])) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="ev-<?= $cod ?>" style="cursor:inherit">
                                    <span class="badge bg-primary me-1"><?= $cod ?></span>
                                    <span style="font-size:.85rem"><?= $desc ?></span>
                                    <?php if ($temDados && !in_array($cod, ['R1000','R2099','R4099','R9000'])): ?>
                                        <i class="bi bi-check-circle-fill text-success ms-1" title="Tem registros"></i>
                                    <?php elseif ($disabled): ?>
                                        <small class="text-muted ms-1">(sem registros)</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Tipo: Inclusão ou Retificação -->
                    <div class="mb-3 p-3 border rounded bg-light">
                        <label class="form-label fw-bold mb-2">
                            <i class="bi bi-arrow-repeat me-1"></i> Tipo de Operação
                        </label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ind_retif" id="retif-1" value="1" checked onchange="document.getElementById('campo-recibo').style.display='none'">
                            <label class="form-check-label" for="retif-1">
                                <strong>Inclusão</strong> — primeira transmissão deste evento
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ind_retif" id="retif-2" value="2" onchange="document.getElementById('campo-recibo').style.display='block'">
                            <label class="form-check-label" for="retif-2">
                                <strong>Retificação</strong> — corrigir evento já transmitido
                            </label>
                        </div>
                        <div id="campo-recibo" style="display:none" class="mt-2">
                            <label class="form-label small mb-1">Número do Recibo Original *</label>
                            <input type="text" name="nr_recibo_original" class="form-control form-control-sm font-monospace" placeholder="Recibo da transmissão original">
                            <div class="form-text">Informe o recibo retornado pela Receita Federal na transmissão original.</div>
                        </div>
                    </div>
                    <!-- Opção de assinatura -->
                    <div class="form-check form-switch mb-3 p-3 ps-5 border rounded bg-light">
                        <input class="form-check-input" type="checkbox" name="assinar" id="chk-assinar" value="1"
                               <?= ($certInfo['valido'] ?? false) ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label" for="chk-assinar">
                            <i class="bi bi-pen me-1"></i> Assinar digitalmente (certificado A1)
                        </label>
                        <?php if (!($certInfo['valido'] ?? false)): ?>
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Certificado não configurado. <a href="/certificados">Configurar</a>
                        </div>
                        <?php endif; ?>
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
            <div class="card-header">
                <span><i class="bi bi-archive me-2"></i>Arquivos Gerados</span>
                <?php if (!empty($arquivosGerados)): ?>
                <a href="/transmissao?competencia_id=<?= $competencia['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-send me-1"></i> Transmitir
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Evento</th><th>Tipo</th><th>Arquivo</th><th>Tamanho</th><th>Assinado</th><th>Gerado em</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($arquivosGerados)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
                            Nenhum arquivo gerado para esta competência.
                        </td></tr>
                        <?php else: foreach ($arquivosGerados as $a): ?>
                      <tr>
                            <td><span class="badge bg-primary"><?= $a['evento'] ?? '—' ?></span></td>
                            <td>
                                <?php if (($a['ind_retif'] ?? 1) == 2): ?>
                                    <span class="badge bg-warning" title="Recibo: <?= htmlspecialchars($a['nr_recibo_original'] ?? '') ?>">
                                        <i class="bi bi-arrow-repeat"></i> Retificação
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">Inclusão</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small text-primary"><?= htmlspecialchars($a['nome_arquivo']) ?></td>
                            <td class="small text-muted"><?= number_format(($a['tamanho'] ?? 0) / 1024, 1) ?> KB</td>
                            <td><?= !empty($a['assinado']) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                            <td>
                                <a href="/download?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success py-0">
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Informações</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>XMLs gerados conforme leiaute EFD-REINF <strong>v2.1.2</strong> (namespace <code>v2_01_02</code>)</li>
                    <li>Ambiente: <strong><?= ($config['reinf']['tp_amb'] ?? 2) === 1 ? 'Produção' : 'Homologação' ?></strong></li>
                    <li>Eventos só podem ser gerados se houver registros na competência (exceto R-1000, fechamentos e exclusão)</li>
                    <li>Após gerar, vá para <a href="/transmissao?competencia_id=<?= $competencia['id'] ?>"><strong>Transmissão</strong></a> para enviar à Receita Federal</li>
                </ul>
            </div>
        </div>
    </div>
</div>