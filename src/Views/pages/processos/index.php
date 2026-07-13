<?php if (!empty($flash)): ?>
<div class="alert alert-<?= ($flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success') ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-folder2-open me-2"></i>R-1070 — Processos Administrativos/Judiciais</h5>
    <a href="/processos/novo" class="btn btn-primary btn-sm">
        <i class="bi bi-plus me-1"></i> Novo Processo
    </a>
</div>

<div class="card">
    <div class="card-header">
        <span>Processos Cadastrados</span>
        <span class="badge bg-secondary ms-1"><?= count($processos) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Contribuinte</th>
                    <th>Tipo</th>
                    <th>Nº Processo</th>
                    <th>Susp. Exigibilidade</th>
                    <th>Status</th>
                    <th>Incluído em</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($processos)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-folder-x display-6 d-block mb-2"></i>
                        Nenhum processo cadastrado.
                        <div class="mt-2"><a href="/processos/novo" class="btn btn-primary btn-sm">Cadastrar primeiro</a></div>
                    </td>
                </tr>
                <?php else: foreach ($processos as $p): ?>
                <tr>
                    <td style="font-size:.85rem"><?= htmlspecialchars($p['razao_social']) ?></td>
                    <td>
                        <?php if ($p['tipo_processo'] == 1): ?>
                            <span class="badge bg-info">Administrativo</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Judicial</span>
                        <?php endif; ?>
                    </td>
                    <td class="font-monospace small"><?= htmlspecialchars($p['numero_processo']) ?></td>
                    <td>
                        <?= $p['indicador_susp_exig'] ? '<i class="bi bi-check-circle-fill text-success"></i> Sim' : '<i class="bi bi-x-circle text-muted"></i> Não' ?>
                    </td>
                    <td>
                        <?php
                        $cores = ['ativo'=>'success','encerrado'=>'secondary','suspenso'=>'warning'];
                        ?>
                        <span class="badge bg-<?= $cores[$p['status']] ?? 'secondary' ?>"><?= ucfirst($p['status']) ?></span>
                    </td>
                    <td class="small text-muted"><?= date('d/m/Y', strtotime($p['data_inclusao'])) ?></td>
                    <td>
                        <a href="/processos/editar?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="/processos/excluir" method="POST" class="d-inline"
                              onsubmit="return confirm('Excluir este processo?')">
                            <?= $csrfField ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Excluir">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Sobre o R-1070</div>
    <div class="card-body small text-muted">
        Use o R-1070 para informar processos administrativos ou judiciais que afetem a tributação
        (suspensão de exigibilidade de INSS, IRRF, CSLL, PIS, COFINS).
        Esses processos podem ser referenciados nos eventos R-2010, R-2020 e R-4020.
        O fato gerador suspenso por decisão judicial não terá tributação retida.
    </div>
</div>