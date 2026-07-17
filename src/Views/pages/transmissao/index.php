<?php $flash = $this->getFlash ?? null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-send me-2"></i>Transmissão SEFAZ</h5>
</div>

<!-- Alerta de ambiente -->
<?php
$tpAmb = $config['reinf']['tp_amb'] ?? 2;
$certOk = !empty($certInfo['valido']);
?>
<div class="alert alert-<?= $tpAmb === 1 ? 'danger' : 'warning' ?> d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-<?= $tpAmb === 1 ? 'exclamation-triangle-fill' : 'info-circle-fill' ?> fs-5"></i>
    <div>
        <strong>Ambiente: <?= $tpAmb === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?></strong>
        <?php if (!$certOk): ?>
            — Certificado digital não configurado. <a href="/certificados">Configurar agora</a>.
            Os envios serão <strong>simulados</strong>.
        <?php else: ?>
            — Certificado: <?= htmlspecialchars($certInfo['titular'] ?? '') ?>,
            válido até <?= $certInfo['validade'] ?? '—' ?>
            (<?= $certInfo['dias_rest'] ?? '?' ?> dias restantes).
        <?php endif; ?>
    </div>
</div>

<?php if ($competencia): ?>
<?php
$pendentes = array_values(array_filter($arquivos, static function ($a) {
    return empty($a['nr_recibo_retornado']) && ($a['evento'] ?? '') !== 'R9000';
}));
$transmitidos = array_values(array_filter($arquivos, static function ($a) {
    return !empty($a['nr_recibo_retornado']) && ($a['evento'] ?? '') !== 'R9000';
}));
?>
<!-- Enviar (XMLs locais, ainda sem recibo) -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cloud-upload me-2"></i>Enviar XMLs — <?= htmlspecialchars($competencia['razao_social']) ?> | <?= $competencia['periodo'] ?></span>
        <a href="/competencias/detalhe?id=<?= $competencia['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
    <div class="card-body">
        <?php if (empty($pendentes)): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
            Nenhum XML pendente de envio.
            <?php if (empty($arquivos)): ?>
                <a href="/gerar?competencia_id=<?= $competenciaId ?>">Gerar XMLs primeiro</a>.
            <?php else: ?>
                Todos já têm recibo — use a seção abaixo para excluir na RFB.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <form method="POST" action="/transmissao/enviar">
    <?= $csrfField ?>
            <input type="hidden" name="competencia_id" value="<?= $competenciaId ?>">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.chk-pend').forEach(c=>c.checked=this.checked)"></th>
                            <th>Evento</th><th>Arquivo</th><th>Tamanho</th>
                            <th>Assinado</th><th>Gerado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendentes as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="arquivos[]" value="<?= $a['id'] ?>" class="chk-pend" checked></td>
                            <td><span class="badge bg-primary"><?= $a['evento'] ?? '—' ?></span></td>
                            <td class="font-monospace small"><?= htmlspecialchars($a['nome_arquivo']) ?></td>
                            <td class="small text-muted"><?= number_format(($a['tamanho'] ?? 0) / 1024, 1) ?> KB</td>
                            <td><?= !empty($a['assinado']) ? '<i class="bi bi-check-circle-fill text-success"></i> Sim' : '<i class="bi bi-x-circle text-muted"></i> Não' ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Confirma o envio do(s) XML(s) selecionado(s) para a SEFAZ?')">
                <i class="bi bi-send me-1"></i>
                <?= $certOk ? 'Enviar para a SEFAZ' : 'Enviar (Simulação)' ?>
            </button>
            <div class="form-text mt-2">
                Para apagar XML local (ainda sem recibo), use a tela <a href="/gerar?competencia_id=<?= $competenciaId ?>">Gerar XMLs</a>.
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Já transmitidos: só R-9000 -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-trash3 me-2"></i>Já transmitidos — excluir na RFB (R-9000)
    </div>
    <div class="card-body">
        <?php if (empty($transmitidos)): ?>
        <div class="text-muted py-3">
            Nenhum XML com recibo nesta competência. Após enviar e consultar o protocolo, eles aparecem aqui.
        </div>
        <?php else: ?>
        <form method="POST" action="/transmissao/excluir-rfb">
    <?= $csrfField ?>
            <input type="hidden" name="competencia_id" value="<?= $competenciaId ?>">
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.chk-tx').forEach(c=>c.checked=this.checked)"></th>
                            <th>Evento</th><th>Arquivo</th><th>Recibo</th><th>Gerado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transmitidos as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="arquivos[]" value="<?= $a['id'] ?>" class="chk-tx"></td>
                            <td><span class="badge bg-primary"><?= $a['evento'] ?? '—' ?></span></td>
                            <td class="font-monospace small"><?= htmlspecialchars($a['nome_arquivo']) ?></td>
                            <td class="font-monospace small text-success"><?= htmlspecialchars($a['nr_recibo_retornado']) ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Excluir na RFB via R-9000? Depois consulte o protocolo — ao aceitar, o sistema apaga localmente também.')">
                <i class="bi bi-trash3 me-1"></i> Excluir na RFB (R-9000)
            </button>
            <div class="form-text mt-2">
                Isso envia o R-9000 para a Receita. Apagar registro de upload ou XML sem recibo continua sendo só local (Eventos / Gerar).
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Consultar protocolo -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-search me-2"></i>Consultar Protocolo</div>
    <div class="card-body">
        <form action="/transmissao/consultar" method="POST" class="row g-2 align-items-end">
    <?= $csrfField ?>
            <input type="hidden" name="competencia_id" value="<?= $competenciaId ?>">
            <div class="col-md-6">
                <label class="form-label">Número do Protocolo</label>
                <input type="text" name="protocolo" class="form-control font-monospace" placeholder="Protocolo recebido no envio" required
                       value="<?= htmlspecialchars($competencia['num_recibo'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i> Consultar
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<?php
$grupos = $gruposContribuintes ?? [];
$basePath = '/transmissao';
$acaoLabel = 'Transmitir';
$acaoIcon = 'bi-send';
$titulo = 'Selecione o contribuinte';
include BASE_PATH . '/src/Views/pages/partials/selecao_contribuinte_competencia.php';
?>
<?php endif; ?>

<!-- Histórico -->
<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Histórico de Transmissões</div>
    <form action="/transmissao/excluir-historico" method="POST"
          onsubmit="return confirm('Apagar os registros selecionados do histórico local?')">
    <?= $csrfField ?>
        <?php if ($competenciaId): ?>
        <input type="hidden" name="competencia_id" value="<?= (int) $competenciaId ?>">
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.chk-hist').forEach(c=>c.checked=this.checked)"></th>
                        <th>Data</th><th>Contribuinte</th><th>Período</th><th>Tipo</th><th>Evento</th><th>Protocolo</th><th>Retorno</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historico)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma transmissão realizada ainda.</td></tr>
                    <?php else: foreach ($historico as $h): ?>
                    <tr>
                        <td><input type="checkbox" name="historico[]" value="<?= (int) $h['id'] ?>" class="chk-hist"></td>
                        <td class="small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td style="font-size:.82rem"><?= htmlspecialchars($h['razao_social'] ?? '') ?></td>
                        <td class="font-monospace small"><?= $h['periodo'] ?? '' ?></td>
                        <td><span class="badge bg-<?= $h['tipo_operacao'] === 'envio' ? 'primary' : 'info' ?>"><?= ucfirst($h['tipo_operacao']) ?></span></td>
                        <td class="font-monospace small"><?= $h['evento'] ?? '' ?></td>
                        <td class="font-monospace small"><?= htmlspecialchars($h['protocolo'] ?? '—') ?></td>
                        <td class="small" title="<?= htmlspecialchars($h['descricao_retorno'] ?? '') ?>">
                            <?= htmlspecialchars(mb_strimwidth($h['descricao_retorno'] ?? '—', 0, 40, '…')) ?>
                        </td>
                        <td>
                            <?php if ($h['sucesso']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> OK</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Erro</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($historico)): ?>
        <div class="card-footer bg-transparent">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Apagar histórico selecionado
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>