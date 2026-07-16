<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

// Carregar .env se existir
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$config = \App\Models\AppConfig::get();

$appEnv = (string) ($config['app']['env'] ?? 'production');
$secret = (string) ($config['app']['secret'] ?? '');
if ($appEnv === 'production' && \App\Services\CertificadoCrypto::isInsecureSecret($secret)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Configuração insegura: defina APP_SECRET forte (≥32 caracteres) no .env antes de usar em production.\n";
    echo "Gere com: php -r \"echo bin2hex(random_bytes(32)), PHP_EOL;\"\n";
    exit(1);
}
if (\App\Services\CertificadoCrypto::isInsecureSecret($secret)) {
    error_log('WARNING: APP_SECRET ausente ou fraco. Defina um valor forte no .env.');
}

if ($appEnv === 'production') {
    ini_set('session.cookie_secure', '1');
}

session_start();

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
        '/processos'             => ['App\\Controllers\\ProcessoController', 'index'],
        '/processos/novo'        => ['App\\Controllers\\ProcessoController', 'novo'],
        '/processos/editar'      => ['App\\Controllers\\ProcessoController', 'editar'],
        // Competências
        '/competencias'          => ['App\\Controllers\\CompetenciaController', 'index'],
        '/competencias/nova'     => ['App\\Controllers\\CompetenciaController', 'nova'],
        '/competencias/detalhe'  => ['App\\Controllers\\CompetenciaController', 'detalhe'],

        // Eventos R-2000
        '/eventos'               => ['App\\Controllers\\EventoController', 'index'],
        '/eventos/r2010'         => ['App\\Controllers\\EventoController', 'r2010'],
        '/eventos/r2020'         => ['App\\Controllers\\EventoController', 'r2020'],
        '/eventos/r2055'         => ['App\\Controllers\\EventoController', 'r2055'],
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
        '/contribuintes/excluir'    => ['App\\Controllers\\ContribuinteController', 'excluir'],
        '/competencias/salvar'      => ['App\\Controllers\\CompetenciaController', 'salvar'],
        '/processos/salvar'         => ['App\\Controllers\\ProcessoController', 'salvar'],
        '/processos/excluir'        => ['App\\Controllers\\ProcessoController', 'excluir'],

        // Eventos R-2000
        '/eventos/r2010/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2010'],
        '/eventos/r2020/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2020'],
        '/eventos/r2055/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2055'],
        '/eventos/r2060/salvar'     => ['App\\Controllers\\EventoController', 'salvarR2060'],
        '/eventos/r2010/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2010'],
        '/eventos/r2020/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2020'],
        '/eventos/r2055/excluir'    => ['App\\Controllers\\EventoController', 'excluirR2055'],
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
        '/transmissao/excluir-rfb'       => ['App\\Controllers\\TransmissaoController', 'excluirRfb'],
        '/transmissao/excluir-arquivos'  => ['App\\Controllers\\TransmissaoController', 'excluirArquivos'],
        '/transmissao/excluir-historico' => ['App\\Controllers\\TransmissaoController', 'excluirHistorico'],

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
    if ($method === 'POST') {
        $controller->verifyCsrf();
    }
    $controller->$action();
} else {
    http_response_code(404);
    include BASE_PATH . '/src/Views/errors/404.php';
}