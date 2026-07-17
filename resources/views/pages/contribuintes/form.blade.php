@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-building me-2"></i>{{ $pageTitle }}</h5>
    <a href="/contribuintes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<div class="card">
    <div class="card-body p-4">
        <form action="/contribuintes/salvar" method="POST" id="formContribuinte">
            @csrf
            @if($contribuinte)
            <input type="hidden" name="id" value="{{ $contribuinte['id'] }}">
            @endif

            <h6 class="mb-3"><i class="bi bi-building me-1"></i> Dados do contribuinte</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de Inscrição *</label>
                    <select name="tipo_contribuinte" id="tipoContrib" class="form-select">
                        @php
                        $tipos = ['1'=>'CNPJ','2'=>'CPF'];
                        @endphp
                        @foreach($tipos as $v => $l)
                        <option value="{{ $v }}" {{ ($contribuinte['tipo_contribuinte'] ?? '1') == $v ? 'selected' : '' }}>
                            {{ $v }} – {{ $l }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" id="labelInscricao">CNPJ / CPF *</label>
                    <input type="text" name="cnpj" id="inputInscricao" class="form-control font-monospace"
                           value="{{ $contribuinte['cnpj'] ?? '' }}"
                           placeholder="00.000.000/0001-00" required>
                    <div id="feedbackValidacao" class="form-text"></div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Classificação Tributária (Tabela 08) *</label>
                    <select name="classificacao_tributos" class="form-select" required>
                        @php
                        $classifs = config('reinf.class_trib', []);
                        $classAtual = $contribuinte['classificacao_tributos'] ?? '99';
                        @endphp
                        @foreach($classifs as $v => $l)
                        <option value="{{ $v }}" {{ $classAtual == $v ? 'selected' : '' }}>
                            {{ $l }}
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text">Maioria das empresas PJ usa <strong>99</strong>.</div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Razão Social *</label>
                    <input type="text" name="razao_social" class="form-control"
                           value="{{ $contribuinte['razao_social'] ?? '' }}"
                           placeholder="Nome completo ou razão social" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" class="form-control"
                           value="{{ $contribuinte['nome_fantasia'] ?? '' }}"
                           placeholder="Opcional">
                </div>
            </div>

            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-sliders me-1"></i> Indicadores R-1000</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Obrigatoriedade ECD *</label>
                    <select name="ind_escrituracao" class="form-select">
                        <option value="0" {{ (int)($contribuinte['ind_escrituracao'] ?? 0) === 0 ? 'selected' : '' }}>0 – Não obrigada à ECD</option>
                        <option value="1" {{ (int)($contribuinte['ind_escrituracao'] ?? 0) === 1 ? 'selected' : '' }}>1 – Obrigada à ECD</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Desoneração da folha *</label>
                    <select name="ind_desoneracao" class="form-select">
                        <option value="0" {{ (int)($contribuinte['ind_desoneracao'] ?? 0) === 0 ? 'selected' : '' }}>0 – Não aplicável</option>
                        <option value="1" {{ (int)($contribuinte['ind_desoneracao'] ?? 0) === 1 ? 'selected' : '' }}>1 – Lei 12.546/2011 (só classTrib 02, 03 ou 99)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Acordo isenção de multa *</label>
                    <select name="ind_acordo_isen_multa" class="form-select">
                        <option value="0" {{ (int)($contribuinte['ind_acordo_isen_multa'] ?? 0) === 0 ? 'selected' : '' }}>0 – Sem acordo</option>
                        <option value="1" {{ (int)($contribuinte['ind_acordo_isen_multa'] ?? 0) === 1 ? 'selected' : '' }}>1 – Com acordo (só classTrib 60)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Situação da PJ *</label>
                    <select name="ind_sit_pj" class="form-select">
                        @php
                        $sits = [
                            0 => '0 – Situação normal',
                            1 => '1 – Extinção',
                            2 => '2 – Fusão',
                            3 => '3 – Cisão',
                            4 => '4 – Incorporação',
                        ];
                        @endphp
                        @foreach($sits as $v => $l)
                        <option value="{{ $v }}" {{ (int)($contribuinte['ind_sit_pj'] ?? 0) === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-person-lines-fill me-1"></i> Contato R-1000</h6>
            <p class="text-muted small mb-3">
                Pessoa física responsável pelo contato com a RFB. Obrigatório para gerar e transmitir o R-1000.
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do contato *</label>
                    <input type="text" name="nome_contato" class="form-control"
                           value="{{ $contribuinte['nome_contato'] ?? '' }}"
                           maxlength="70" placeholder="Nome completo" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">CPF do contato *</label>
                    <input type="text" name="cpf_contato" id="inputCpfContato" class="form-control font-monospace"
                           value="{{ $contribuinte['cpf_contato'] ?? '' }}"
                           placeholder="000.000.000-00" maxlength="14" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefone (com DDD) *</label>
                    <input type="text" name="telefone" id="inputTelefone" class="form-control font-monospace"
                           value="{{ $contribuinte['telefone'] ?? '' }}"
                           placeholder="(00) 00000-0000" maxlength="15" required>
                    <div class="form-text">Mínimo 10 dígitos (DDD + número).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ $contribuinte['email'] ?? '' }}"
                           maxlength="60" placeholder="contato@empresa.com.br">
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

<script>
(function() {
    const tipoContrib = document.getElementById('tipoContrib');
    const input       = document.getElementById('inputInscricao');
    const label       = document.getElementById('labelInscricao');
    const feedback    = document.getElementById('feedbackValidacao');
    const cpfContato  = document.getElementById('inputCpfContato');
    const telefone    = document.getElementById('inputTelefone');

    if (cpfContato) {
        cpfContato.addEventListener('input', function() {
            this.value = mascarCPF(this.value);
        });
        if (cpfContato.value) cpfContato.value = mascarCPF(cpfContato.value);
    }
    if (telefone) {
        telefone.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 10) {
                this.value = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
            } else if (v.length > 6) {
                this.value = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (v.length > 2) {
                this.value = v.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
            } else {
                this.value = v;
            }
        });
    }

    const config = {
        '1': { nome: 'CNPJ',  maxLen: 14, placeholder: '00.000.000/0001-00', mascara: mascarCNPJ,  valida: validaCNPJ  },
        '2': { nome: 'CPF',   maxLen: 11, placeholder: '000.000.000-00',     mascara: mascarCPF,   valida: validaCPF   },
    };

    function mascarCNPJ(v) {
        v = v.replace(/\D/g,'').slice(0,14);
        v = v.replace(/^(\d{2})(\d)/,'$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
        v = v.replace(/(\d{4})(\d)/,'$1-$2');
        return v;
    }
    function mascarCPF(v) {
        v = v.replace(/\D/g,'').slice(0,11);
        v = v.replace(/(\d{3})(\d)/,'$1.$2');
        v = v.replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4');
        return v;
    }

    function validaCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g,'');
        if (cnpj.length !== 14) return false;
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        let pesos1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        let pesos2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        let soma = 0;
        for (let i = 0; i < 12; i++) soma += parseInt(cnpj[i]) * pesos1[i];
        let d1 = (soma % 11 < 2) ? 0 : 11 - (soma % 11);
        if (parseInt(cnpj[12]) !== d1) return false;
        soma = 0;
        for (let i = 0; i < 13; i++) soma += parseInt(cnpj[i]) * pesos2[i];
        let d2 = (soma % 11 < 2) ? 0 : 11 - (soma % 11);
        return parseInt(cnpj[13]) === d2;
    }
    function validaCPF(cpf) {
        cpf = cpf.replace(/\D/g,'');
        if (cpf.length !== 11) return false;
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        let soma = 0;
        for (let i = 0; i < 9; i++) soma += parseInt(cpf[i]) * (10 - i);
        let d1 = (soma % 11 < 2) ? 0 : 11 - (soma % 11);
        if (parseInt(cpf[9]) !== d1) return false;
        soma = 0;
        for (let i = 0; i < 10; i++) soma += parseInt(cpf[i]) * (11 - i);
        let d2 = (soma % 11 < 2) ? 0 : 11 - (soma % 11);
        return parseInt(cpf[10]) === d2;
    }

    function atualizar() {
        const cfg = config[tipoContrib.value] || config['1'];
        label.textContent = cfg.nome + ' *';
        input.placeholder = cfg.placeholder;
        input.value = cfg.mascara(input.value);
        validar();
    }

    function validar() {
        const cfg = config[tipoContrib.value] || config['1'];
        const digits = input.value.replace(/\D/g,'');
        if (!digits) { feedback.textContent = ''; feedback.className = 'form-text'; return; }
        if (cfg.valida(digits)) {
            feedback.textContent = cfg.nome + ' válido';
            feedback.className = 'form-text text-success';
        } else {
            feedback.textContent = cfg.nome + ' inválido';
            feedback.className = 'form-text text-danger';
        }
    }

    tipoContrib.addEventListener('change', atualizar);
    input.addEventListener('input', function() {
        const cfg = config[tipoContrib.value] || config['1'];
        this.value = cfg.mascara(this.value);
        validar();
    });
    atualizar();
})();
</script>
