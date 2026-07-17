<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – STHEPSON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/css/app.css?v=3">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <img src="/img/logo-sthepson.png?v=3" alt="STHEPSON" class="login-brand-logo"
                 width="180" height="45" style="width:180px;height:auto;max-width:100%;display:block;margin:0 auto 10px;">
            <p>EFD-Reinf · Geração e Gestão</p>
        </div>

        @if(!empty($flash))
        <div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert py-2 small">
            {{ $flash['mensagem'] }}
        </div>
        @endif

        <form action="/login" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="seu@email.com.br" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
        </form>
        <p class="text-center text-muted small mt-4 mb-0">
            Acesso restrito a usuários cadastrados
        </p>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
