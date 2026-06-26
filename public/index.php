<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

// Carregar .env se existir
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

session_start();

$config = \App\Models\AppConfig::get();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$uri    = '/' . ltrim($uri, '/');

$routes = [
    'GET' => [
        '/'                      => ['App\\Controllers\\HomeController', 'index'],
        '/login'                 => ['App\\Controllers\\AuthController', 'loginForm'],
        '/logout'                => ['App\\Controllers\\AuthController', 'logout'],
        '/dashboard'             => ['App\\Controllers\\DashboardController', 'index'],
        '/gerar/validar'         => ['App\\Controllers\\GeracaoController', 'validar'],
        '/gerar/xsd'             => ['App\\Controllers\\GeracaoController', 'statusXsd'],
        // Contribuintes
        '/contribuintes'         => ['App\\Controllers\\ContribuinteController', 'index'],
        '/contribuintes/novo'    => ['App\\Controllers\\ContribuinteController', 'novo'],
        '/contribuintes/editar'  => ['App\\Controllers\\ContribuinteController', 'editar'],
        '/contribuintes/excluir' => ['App\\Controllers\\ContribuinteController', 'excluir'],
        '/processos'             => ['App\\Controllers\\ProcessoController', 'index'],
        '/processos/novo'        => ['App\\Controllers\\ProcessoController', 'novo'],
        '/processos/editar'      => ['App\\Controllers\\ProcessoController', 'editar'],
        '/processos/excluir'     => ['App\\Controllers\\ProcessoController', 'excluir'],
        // Competências
        '/competencias'          => ['App\\Controllers\\CompetenciaController', 'index'],
        '/competencias/nova'     => ['App\\Controllers\\CompetenciaController', 'nova'],
        '/competencias/detalhe'  => ['App\\Controllers\\CompetenciaController', 'detalhe'],

        // Eventos R-2000
        '/eventos'               => ['App\\Controllers\\EventoController', 'index'],
        '/eventos/r2010'         => ['App\\Controllers\\EventoController', 'r2010'],
        '/eventos/r2020'         => ['App\\Controllers\\EventoController', 'r2020'],
        '/eventos/r2060'         => ['App\\Controllers\\EventoController', 'r2060'],

        // Eventos R-4000
        '/eventos/r4010'         => ['App\\Controllers\\EventoController', 'r4010'],
        '/eventos/r4020'         => ['App\\Controllers\\EventoController', 'r4020'],

        // Importação
        '/importar'              => ['App\\Controllers\\ImportacaoController', 'index'],

        // Geração XML
        '/gerar'                 => ['App\\Controllers\\GeracaoController', 'index'],
        '/download'              => ['App\\Controllers\\GeracaoController', 'download'],

        // Transmissão
        '/transmissao'           => ['App\\Controllers\\TransmissaoController', 'index'],

        // Certificados
        '/certificados'          => ['App\\Controllers\\CertificadoController', 'index'],

        // Usuários
        '/usuarios'              => ['App\\Controllers\\UsuarioController', 'index'],
        '/usuarios/novo'         => ['App\\Controllers\\UsuarioController', 'novo'],
        '/perfil'                => ['App\\Controllers\\UsuarioController', 'perfil'],
    ],
    'POST' => [
        '/login'                    => ['App\\Controllers\\AuthController', 'login'],
        '/contribuintes/salvar'     => ['App\\Controllers\\ContribuinteController', 'salvar'],
        '/competencias/salvar'      => ['App\\Controllers\\CompetenciaController', 'salvar'],
        '/processos/salvar'      => ['App\\Controllers\\ProcessoController', 'salvar'],

        // Eventos R-2000
        '/eventos/r2010/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2010'],
        '/eventos/r2020/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2020'],
        '/eventos/r2060/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2060'],
        '/eventos/r2010/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2010'],
        '/eventos/r2020/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2020'],
        '/eventos/r2060/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2060'],

        // Eventos R-4000
        '/eventos/r4010/salvar'     => ['App\\Controllers\\EventoController', 'salvarR4010'],
        '/eventos/r4010/excluir'    => ['App\\Controllers\\EventoController', 'excluirR4010'],
        '/eventos/r4020/salvar'     => ['App\\Controllers\\EventoController', 'salvarR4020'],
        '/eventos/r4020/excluir'    => ['App\\Controllers\\EventoController', 'excluirR4020'],

        // Importação e Geração
        '/importar/processar'       => ['App\\Controllers\\ImportacaoController', 'processar'],
        '/gerar/xml'                => ['App\\Controllers\\GeracaoController', 'gerar'],

        // Transmissão
        '/transmissao/enviar'       => ['App\\Controllers\\TransmissaoController', 'enviar'],
        '/transmissao/consultar'    => ['App\\Controllers\\TransmissaoController', 'consultar'],

        // Certificados
        '/certificados/upload'      => ['App\\Controllers\\CertificadoController', 'upload'],

        // Usuários
        '/usuarios/salvar'          => ['App\\Controllers\\UsuarioController', 'salvar'],
        '/perfil/salvar'            => ['App\\Controllers\\UsuarioController', 'salvarPerfil'],
    ],
];

$handler = $routes[$method][$uri] ?? null;

if ($handler) {
    [$class, $action] = $handler;
    $controller = new $class($config);
    $controller->$action();
} else {
    http_response_code(404);
    include BASE_PATH . '/src/Views/errors/404.php';
}