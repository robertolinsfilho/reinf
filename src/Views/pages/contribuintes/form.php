<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-building me-2"></i><?= $pageTitle ?></h5>
    <a href="/contribuintes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<div class="card">
    <div class="card-body p-4">
        <form action="/contribuintes/salvar" method="POST">
            <?php if ($contribuinte): ?>
            <input type="hidden" name="id" value="<?= $contribuinte['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">CNPJ / CPF *</label>
                    <input type="text" name="cnpj" class="form-control font-monospace"
                           data-mask="cnpj"
                           value="<?= htmlspecialchars($contribuinte['cnpj'] ?? '') ?>"
                           placeholder="00.000.000/0001-00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Inscrição *</label>
                    <select name="tipo_contribuinte" class="form-select">
                        <?php
                        $tipos = ['1'=>'CNPJ','2'=>'CPF','3'=>'CAEPF','4'=>'CNO','5'=>'CGC','6'=>'CEI'];
                        foreach ($tipos as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($contribuinte['tipo_contribuinte'] ?? '1') == $v ? 'selected' : '' ?>>
                            <?= $v ?> – <?= $l ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Classificação Tributária</label>
                    <select name="classificacao_tributos" class="form-select">
                        <?php
                        $classifs = [
                            '01'=>'01 – Lucro Real',
                            '02'=>'02 – Lucro Presumido',
                            '03'=>'03 – Lucro Arbitrado',
                            '04'=>'04 – Simples Nacional',
                            '05'=>'05 – MEI',
                            '06'=>'06 – Imune / Isenta',
                            '07'=>'07 – Órgão Público',
                            '08'=>'08 – Produtor Rural PF',
                            '09'=>'09 – Condomínio',
                        ];
                        foreach ($classifs as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($contribuinte['classificacao_tributos'] ?? '01') == $v ? 'selected' : '' ?>>
                            <?= $l ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Razão Social *</label>
                    <input type="text" name="razao_social" class="form-control"
                           value="<?= htmlspecialchars($contribuinte['razao_social'] ?? '') ?>"
                           placeholder="Nome completo ou razão social" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" class="form-control"
                           value="<?= htmlspecialchars($contribuinte['nome_fantasia'] ?? '') ?>"
                           placeholder="Opcional">
                </div>
            </div>

            <hr class="my-4">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
                <a href="/contribuintes" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
