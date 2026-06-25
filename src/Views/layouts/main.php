<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'EFD REINF' ?> – <?= $appName ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/app.css">
</head>
<body class="<?= isset($usuario) ? 'has-sidebar' : '' ?>">

<?php if (isset($usuario)): ?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-receipt-cutoff"></i>
        <span>EFD REINF</span>
    </div>
    <nav class="sidebar-nav">
        <a href="/dashboard" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/contribuintes" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'contribuinte') ? 'active' : '' ?>">
            <i class="bi bi-building"></i> Contribuintes
        </a>
        <a href="/competencias" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'competencia') ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i> Competências
        </a>
        <a href="/processos" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'processo') ? 'active' : '' ?>">
            <i class="bi bi-folder2-open"></i> R-1070 Processos
        </a>

        <div class="nav-label">PREVIDÊNCIA (INSS)</div>
        <a href="/eventos/r2010" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r2010') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-2010 Serv. Tomados
        </a>
        <a href="/eventos/r2020" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r2020') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-2020 Serv. Prestados
        </a>
        <a href="/eventos/r2060" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r2060') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-2060 CPRB
        </a>

        <div class="nav-label">IRRF / CSRF (DIRF)</div>
        <a href="/eventos/r4010" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r4010') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-4010 Pagtos PF
        </a>
        <a href="/eventos/r4020" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r4020') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-4020 Pagtos PJ
        </a>

        <div class="nav-label">PROCESSAMENTO</div>
        <a href="/importar" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'importar') ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-excel"></i> Importar Excel
        </a>
        <a href="/gerar" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'gerar') ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-code"></i> Gerar XML
        </a>
        <a href="/transmissao" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'transmissao') ? 'active' : '' ?>">
            <i class="bi bi-send"></i> Transmissão
        </a>
        <a href="/certificados" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'certificado') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> Certificado A1
        </a>

        <div class="nav-label">CONSULTAS</div>
        <a href="/naturezas" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'naturezas') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Naturezas de Rendimento
        </a>

        <?php if (($usuario['perfil'] ?? '') === 'admin'): ?>
        <div class="nav-label">ADMIN</div>
        <a href="/usuarios" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], 'usuario') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Usuários
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/perfil"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($usuario['nome']) ?></a>
        <a href="/logout"><i class="bi bi-box-arrow-right"></i> Sair</a>
    </div>
</div>

<!-- Main content -->
<div class="main-content">
    <div class="topbar">
        <button class="btn btn-link sidebar-toggle" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h5 class="mb-0"><?= $pageTitle ?? '' ?></h5>
    </div>
    <div class="page-content">
        <?= $content ?>
    </div>
</div>

<?php else: ?>
<?= $content ?>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('expanded');
}
document.querySelectorAll('input[data-mask="cnpj"]').forEach(el => {
    el.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,14);
        v = v.replace(/^(\d{2})(\d)/,'$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
        v = v.replace(/(\d{4})(\d)/,'$1-$2');
        this.value = v;
    });
});
document.querySelectorAll('input[data-mask="cpf"]').forEach(el => {
    el.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,11);
        v = v.replace(/(\d{3})(\d)/,'$1.$2');
        v = v.replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4');
        this.value = v;
    });
});
</script>
</body>
</html>