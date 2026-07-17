<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'EFD REINF' ?> – <?= $appName ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/app.css?v=4">
</head>
<body class="<?= isset($usuario) ? 'has-sidebar' : '' ?>">

<?php if (isset($usuario)): ?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="/img/logo-sthepson.png?v=3" alt="STHEPSON" class="brand-logo"
             width="160" height="40" style="width:160px;height:auto;max-width:100%;display:block;">
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
        <a href="/eventos/r2055" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], 'r2055') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> R-2055 Aq. Prod. Rural
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
        <a href="/gerar/xsd" class="nav-item sub <?= str_contains($_SERVER['REQUEST_URI'], '/gerar/xsd') ? 'active' : '' ?>">
            <i class="bi bi-arrow-right-short"></i> Status XSDs
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

<button type="button" class="sidebar-backdrop" id="sidebar-backdrop" aria-label="Fechar menu" onclick="closeSidebar()"></button>

<!-- Main content -->
<div class="main-content">
    <div class="topbar">
        <button type="button" class="btn btn-link sidebar-toggle text-dark" onclick="toggleSidebar()" aria-label="Abrir menu">
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
function isMobileNav() {
    return window.matchMedia('(max-width: 768px)').matches;
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar) return;
    sidebar.classList.remove('open');
    backdrop?.classList.remove('show');
    document.body.style.overflow = '';
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main = document.querySelector('.main-content');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar || !main) return;

    if (isMobileNav()) {
        const opening = !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', opening);
        backdrop?.classList.toggle('show', opening);
        document.body.style.overflow = opening ? 'hidden' : '';
        return;
    }

    sidebar.classList.toggle('collapsed');
    main.classList.toggle('expanded');
}
document.getElementById('sidebar')?.querySelectorAll('a.nav-item').forEach((link) => {
    link.addEventListener('click', () => {
        if (isMobileNav()) closeSidebar();
    });
});
window.addEventListener('resize', () => {
    if (!isMobileNav()) closeSidebar();
});
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