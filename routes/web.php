<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\CompetenciaController;
use App\Http\Controllers\ContribuinteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GeracaoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\TransmissaoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

// Autenticação
Route::get('/login', [AuthController::class, 'loginForm']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout']);

// Rotas protegidas (login obrigatório + troca de senha forçada)
Route::middleware(['auth', 'force.password'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Contribuintes
    Route::get('/contribuintes', [ContribuinteController::class, 'index']);
    Route::get('/contribuintes/novo', [ContribuinteController::class, 'novo']);
    Route::get('/contribuintes/editar', [ContribuinteController::class, 'editar']);
    Route::post('/contribuintes/salvar', [ContribuinteController::class, 'salvar']);
    Route::post('/contribuintes/excluir', [ContribuinteController::class, 'excluir']);

    // Processos (R-1070)
    Route::get('/processos', [ProcessoController::class, 'index']);
    Route::get('/processos/novo', [ProcessoController::class, 'novo']);
    Route::get('/processos/editar', [ProcessoController::class, 'editar']);
    Route::post('/processos/salvar', [ProcessoController::class, 'salvar']);
    Route::post('/processos/excluir', [ProcessoController::class, 'excluir']);

    // Competências
    Route::get('/competencias', [CompetenciaController::class, 'index']);
    Route::get('/competencias/nova', [CompetenciaController::class, 'nova']);
    Route::get('/competencias/detalhe', [CompetenciaController::class, 'detalhe']);
    Route::post('/competencias/salvar', [CompetenciaController::class, 'salvar']);

    // Eventos
    Route::get('/eventos', [EventoController::class, 'index']);

    // Eventos R-2000
    Route::get('/eventos/r2010', [EventoController::class, 'r2010']);
    Route::get('/eventos/r2020', [EventoController::class, 'r2020']);
    Route::get('/eventos/r2055', [EventoController::class, 'r2055']);
    Route::get('/eventos/r2060', [EventoController::class, 'r2060']);
    Route::post('/eventos/r2010/salvar', [EventoController::class, 'salvarR2010']);
    Route::post('/eventos/r2020/salvar', [EventoController::class, 'salvarR2020']);
    Route::post('/eventos/r2055/salvar', [EventoController::class, 'salvarR2055']);
    Route::post('/eventos/r2060/salvar', [EventoController::class, 'salvarR2060']);
    Route::post('/eventos/r2010/excluir', [EventoController::class, 'excluirR2010']);
    Route::post('/eventos/r2020/excluir', [EventoController::class, 'excluirR2020']);
    Route::post('/eventos/r2055/excluir', [EventoController::class, 'excluirR2055']);
    Route::post('/eventos/r2060/excluir', [EventoController::class, 'excluirR2060']);

    // Eventos R-4000
    Route::get('/eventos/r4010', [EventoController::class, 'r4010']);
    Route::get('/eventos/r4020', [EventoController::class, 'r4020']);
    Route::post('/eventos/r4010/salvar', [EventoController::class, 'salvarR4010']);
    Route::post('/eventos/r4010/excluir', [EventoController::class, 'excluirR4010']);
    Route::post('/eventos/r4020/salvar', [EventoController::class, 'salvarR4020']);
    Route::post('/eventos/r4020/excluir', [EventoController::class, 'excluirR4020']);

    // Importação
    Route::get('/importar', [ImportacaoController::class, 'index']);
    Route::post('/importar/processar', [ImportacaoController::class, 'processar']);
    Route::post('/importar/iniciar', [ImportacaoController::class, 'iniciar']);
    Route::post('/importar/chunk', [ImportacaoController::class, 'chunk']);

    // Geração XML
    Route::get('/gerar', [GeracaoController::class, 'index']);
    Route::get('/gerar/validar', [GeracaoController::class, 'validar']);
    Route::get('/gerar/xsd', [GeracaoController::class, 'statusXsd']);
    Route::post('/gerar/xml', [GeracaoController::class, 'gerar']);
    Route::get('/download', [GeracaoController::class, 'download']);

    // Transmissão
    Route::get('/transmissao', [TransmissaoController::class, 'index']);
    Route::post('/transmissao/enviar', [TransmissaoController::class, 'enviar']);
    Route::post('/transmissao/consultar', [TransmissaoController::class, 'consultar']);
    Route::post('/transmissao/excluir-rfb', [TransmissaoController::class, 'excluirRfb']);
    Route::post('/transmissao/excluir-arquivos', [TransmissaoController::class, 'excluirArquivos']);
    Route::post('/transmissao/excluir-historico', [TransmissaoController::class, 'excluirHistorico']);

    // Certificados
    Route::get('/certificados', [CertificadoController::class, 'index']);
    Route::post('/certificados/upload', [CertificadoController::class, 'upload']);

    // Perfil do usuário
    Route::get('/perfil', [UsuarioController::class, 'perfil']);
    Route::post('/perfil/salvar', [UsuarioController::class, 'salvarPerfil']);

    // Usuários (restrito a administradores)
    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::get('/usuarios/novo', [UsuarioController::class, 'novo']);
        Route::post('/usuarios/salvar', [UsuarioController::class, 'salvar']);
    });
});
