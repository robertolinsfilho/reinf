<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

// Carregar .env se existir
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Iniciar sessão
session_start();

// Configurações
$config = require BASE_PATH . '/config/app.php';

// Roteamento simples
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove prefixo de subdiretório se houver
$uri = '/' . ltrim($uri, '/');

// Rotas
$routes = [
    'GET' => [
        '/'                      => ['App\\Controllers\\HomeController', 'index'],
        '/login'                 => ['App\\Controllers\\AuthController', 'loginForm'],
        '/logout'                => ['App\\Controllers\\AuthController', 'logout'],
        '/dashboard'             => ['App\\Controllers\\DashboardController', 'index'],
        '/contribuintes'         => ['App\\Controllers\\ContribuinteController', 'index'],
        '/contribuintes/novo'    => ['App\\Controllers\\ContribuinteController', 'novo'],
        '/contribuintes/editar'  => ['App\\Controllers\\ContribuinteController', 'editar'],
        '/contribuintes/excluir' => ['App\\Controllers\\ContribuinteController', 'excluir'],
        '/competencias'          => ['App\\Controllers\\CompetenciaController', 'index'],
        '/competencias/nova'     => ['App\\Controllers\\CompetenciaController', 'nova'],
        '/competencias/detalhe'  => ['App\\Controllers\\CompetenciaController', 'detalhe'],
        '/eventos'               => ['App\\Controllers\\EventoController', 'index'],
        '/eventos/r2010'         => ['App\\Controllers\\EventoController', 'r2010'],
        '/eventos/r2020'         => ['App\\Controllers\\EventoController', 'r2020'],
        '/eventos/r2060'         => ['App\\Controllers\\EventoController', 'r2060'],
        '/eventos/r4020'         => ['App\\Controllers\\EventoController', 'r4020'],        // NOVO
        '/eventos/r4020/excluir' => ['App\\Controllers\\EventoController', 'excluirR4020'], // NOVO
        '/eventos/natureza'      => ['App\\Controllers\\EventoController', 'naturezaJson'], // NOVO (API)
        '/importar'              => ['App\\Controllers\\ImportacaoController', 'index'],
        '/gerar'                 => ['App\\Controllers\\GeracaoController', 'index'],
        '/download'              => ['App\\Controllers\\GeracaoController', 'download'],
        '/usuarios'              => ['App\\Controllers\\UsuarioController', 'index'],
        '/usuarios/novo'         => ['App\\Controllers\\UsuarioController', 'novo'],
        '/perfil'                => ['App\\Controllers\\UsuarioController', 'perfil'],
    ],
    'POST' => [
        '/login'                 => ['App\\Controllers\\AuthController', 'login'],
        '/contribuintes/salvar'  => ['App\\Controllers\\ContribuinteController', 'salvar'],
        '/competencias/salvar'   => ['App\\Controllers\\CompetenciaController', 'salvar'],
        '/eventos/r2010/salvar'  => ['App\\Controllers\\EventoController', 'salvarR2010'],
        '/eventos/r2020/salvar'  => ['App\\Controllers\\EventoController', 'salvarR2020'],
        '/eventos/r2060/salvar'  => ['App\\Controllers\\EventoController', 'salvarR2060'],
        '/eventos/r4020/salvar'  => ['App\\Controllers\\EventoController', 'salvarR4020'], // NOVO
        '/importar/processar'    => ['App\\Controllers\\ImportacaoController', 'processar'],
        '/gerar/xml'             => ['App\\Controllers\\GeracaoController', 'gerarXml'],
        '/usuarios/salvar'       => ['App\\Controllers\\UsuarioController', 'salvar'],
        '/perfil/salvar'         => ['App\\Controllers\\UsuarioController', 'salvarPerfil'],
    ],
];

// Resolver rota
$handler = $routes[$method][$uri] ?? null;

if ($handler) {
    [$class, $action] = $handler;
    $controller = new $class($config);
    $controller->$action();
} else {
    // 404
    // R-4010 (Pagamentos PF)
    '/eventos/r4010'               => ['EventoController', 'r4010'],
    '/eventos/r4010/salvar'        => ['EventoController', 'salvarR4010'],
    '/eventos/r4010/excluir'       => ['EventoController', 'excluirR4010'],

    // R-4020 (Pagamentos PJ)
    '/eventos/r4020'               => ['EventoController', 'r4020'],
    '/eventos/r4020/salvar'        => ['EventoController', 'salvarR4020'],
    '/eventos/r4020/excluir'       => ['EventoController', 'excluirR4020'],

    // Transmissão
    '/transmissao'                 => ['TransmissaoController', 'index'],
    '/transmissao/enviar'          => ['TransmissaoController', 'enviar'],
    '/transmissao/consultar'       => ['TransmissaoController', 'consultar'],

    // Certificados
    '/certificados'                => ['CertificadoController', 'index'],
    '/certificados/upload'         => ['CertificadoController', 'upload'],
    http_response_code(404);
    include BASE_PATH . '/src/Views/errors/404.php';
}