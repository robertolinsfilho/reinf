<div class="page-header">
    <h5><i class="bi bi-list-ul me-2"></i>Eventos EFD REINF</h5>
</div>
<div class="row g-3">
@php
$eventos = [
    ['R-1000','Informações do Contribuinte','primary','Cadastro inicial do contribuinte no REINF.'],
    ['R-2010','Retenções INSS – Contratados','success','Retenção de contribuição previdenciária sobre serviços tomados.'],
    ['R-2020','Retenções INSS – Contratantes','success','Retenção sofrida como prestador de serviços.'],
    ['R-2050','Comercialização Produção Rural PJ','info','Comercialização de produção rural por Pessoa Jurídica.'],
    ['R-2055','Aquisição Produção Rural','info','Aquisição de produção rural de Pessoa Física.'],
    ['R-2060','CPRB – Contrib. Prev. Receita Bruta','warning','Desonerações da folha de pagamento.'],
    ['R-3010','Receitas de Espetáculos Desportivos','secondary','Clubes de futebol – receitas de espetáculos.'],
    ['R-9000','Exclusão de Eventos','danger','Cancelamento/exclusão de evento já enviado.'],
];
@endphp
@foreach($eventos as [$cod, $nome, $cor, $desc])
<div class="col-md-6 col-lg-4">
    <div class="card h-100">
        <div class="card-body">
            <span class="badge bg-{{ $cor }} mb-2">{{ $cod }}</span>
            <h6 class="card-title">{{ $nome }}</h6>
            <p class="card-text text-muted small">{{ $desc }}</p>
        </div>
        @if(in_array($cod, ['R-2010','R-2020','R-2055','R-2060']))
        <div class="card-footer bg-transparent border-0">
            <a href="/eventos/{{ strtolower(str_replace('-','', $cod)) }}?competencia_id=" class="btn btn-sm btn-outline-{{ $cor }}">
                Acessar →
            </a>
        </div>
        @endif
    </div>
</div>
@endforeach
</div>
