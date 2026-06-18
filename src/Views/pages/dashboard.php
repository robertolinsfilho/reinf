<?php
$flash = $this->getFlash ?? null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<!-- Cards resumo -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center p-4 border-primary">
            <i class="bi bi-building display-6 text-primary"></i>
            <h3 class="mt-2 mb-0"><?= $totalContribuintes ?? 0 ?></h3>
            <small class="text-muted">Contribuintes</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-4 border-success">
            <i class="bi bi-calendar3 display-6 text-success"></i>
            <h3 class="mt-2 mb-0"><?= $totalCompetencias ?? 0 ?></h3>
            <small class="text-muted">Competências</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-4 border-info">
            <i class="bi bi-check-circle display-6 text-info"></i>
            <h3 class="mt-2 mb-0"><?= $totalTransmitidos ?? 0 ?></h3>
            <small class="text-muted">Transmitidos</small>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Últimas competências -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Últimas Competências</span>
                <a href="/competencias/nova" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Nova</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Contribuinte</th><th>Período</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($competencias)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma competência criada ainda.</td></tr>
                        <?php else: foreach ($competencias as $c): ?>
                        <tr>
                            <td style="font-size:.85rem"><?= htmlspecialchars($c['razao_social']) ?></td>
                            <td class="font-monospace"><?= $c['periodo'] ?></td>
                            <td>
                                <?php
                                $cores = ['aberto'=>'secondary','fechado'=>'warning','transmitido'=>'success','retificado'=>'info'];
                                ?>
                                <span class="badge bg-<?= $cores[$c['status']] ?? 'secondary' ?>"><?= ucfirst($c['status']) ?></span>
                            </td>
                            <td><a href="/competencias/detalhe?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Painel lateral -->
    <div class="col-lg-5">
        <!-- Ações Rápidas -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Ações Rápidas</div>
            <div class="list-group list-group-flush">
                <a href="/importar" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel text-success"></i> Importar Planilha Excel
                </a>
                <a href="/gerar" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-code text-primary"></i> Gerar Arquivo XML
                </a>
                <a href="/transmissao" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-send text-info"></i> Transmitir para SEFAZ
                </a>
                <a href="/contribuintes/novo" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-building text-muted"></i> Novo Contribuinte
                </a>
                <a href="/competencias/nova" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-plus text-muted"></i> Nova Competência
                </a>
                <a href="/certificados" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock text-warning"></i> Certificado Digital A1
                </a>
            </div>
        </div>

        <!-- Eventos Suportados -->
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Eventos Suportados</div>
            <div class="list-group list-group-flush">
                <?php
                $eventos = [
                    ['R-1000', 'Informações do Contribuinte',     'primary'],
                    ['R-2010', 'Retenções INSS – Serv. Tomados',  'success'],
                    ['R-2020', 'Retenções INSS – Serv. Prestados','success'],
                    ['R-2060', 'CPRB – Contribuição Prev.',       'warning'],
                    ['R-2099', 'Fechamento Série R-2000',         'secondary'],
                    ['R-4010', 'Pagamentos PF – IRRF',            'info'],
                    ['R-4020', 'Pagamentos PJ – IRRF/CSRF',       'info'],
                    ['R-4099', 'Fechamento Série R-4000',         'secondary'],
                    ['R-9000', 'Exclusão de Eventos',             'danger'],
                ];
                foreach ($eventos as [$cod, $desc, $cor]): ?>
                <div class="list-group-item d-flex align-items-center gap-2 py-2">
                    <span class="badge bg-<?= $cor ?>"><?= $cod ?></span>
                    <small><?= $desc ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>