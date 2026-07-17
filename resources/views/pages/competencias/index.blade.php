@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-calendar3 me-2"></i>Competências</h5>
    <a href="/competencias/nova" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nova Competência</a>
</div>

@include('pages.partials.selecao_contribuinte_competencia', [
    'grupos'     => $gruposContribuintes ?? [],
    'basePath'   => '/competencias/detalhe',
    'paramName'  => 'id',
    'acaoLabel'  => 'Abrir competência',
    'acaoIcon'   => 'bi-eye',
    'titulo'     => 'Selecione o contribuinte',
])
