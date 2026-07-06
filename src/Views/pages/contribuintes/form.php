<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'sucesso' ? 'success' : 'danger' ?> flash-alert">
    <?= htmlspecialchars($flash['mensagem']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <h5><i class="bi bi-building me-2"></i><?= $pageTitle ?></h5>
    <a href="/contribuintes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

<div class="card">
    <div class="card-body p-4">
        <form action="/contribuintes/salvar" method="POST" id="formContribuinte">
            <?php if ($contribuinte): ?>
            <input type="hidden" name="id" value="<?= $contribuinte['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de Inscrição *</label>
                    <select name="tipo_contribuinte" id="tipoContrib" class="form-select">
                        <?php
                        $tipos = ['1'=>'CNPJ','2'=>'CPF','3'=>'CAEPF','4'=>'CNO','5'=>'CGC','6'=>'CEI'];
                        foreach ($tipos as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($contribuinte['tipo_contribuinte'] ?? '1') == $v ? 'selected' : '' ?>>
                            <?= $v ?> – <?= $l ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" id="labelInscricao">CNPJ / CPF *</label>
                    <input type="text" name="cnpj" id="inputInscricao" class="form-control font-monospace"
                           value="<?= htmlspecialchars($contribuinte['cnpj'] ?? '') ?>"
                           placeholder="00.000.000/0001-00" required>
                    <div id="feedbackValidacao" class="form-text"></div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Classificação Tributária</label>
                    <select name="classificacao_tributos" class="form-select">
                        <?php
                        $classifs = [
                            '01'=>'01 – Lucro Real',
                            '02'=>'02 – Lucro Presumido',
                            '03'=>'03 – Lucro Arbitrado',
                            '04'=>'04 – Simples Nacional',
                            '05'=>'05 – MEI',
                            '06'=>'06 – Imune / Isenta',
                            '07'=>'07 – Órgão Público',
                            '08'=>'08 – Produtor Rural PF',
                            '09'=>'09 – Condomínio',
                        ];
                        foreach ($classifs as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($contribuinte['classificacao_tributos'] ?? '01') == $v ? 'selected' : '' ?>>
                            <?= $l ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Razão Social *</label>
                    <input type="text" name="razao_social" class="form-control"
                           value="<?= htmlspecialchars($contribuinte['razao_social'] ?? '') ?>"
                           placeholder="Nome completo ou razão social" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" class="form-control"
                           value="<?= htmlspecialchars($contribuinte['nome_fantasia'] ?? '') ?>"
                           placeholder="Opcional">
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

    // Configurações por tipo
    // 1=CNPJ (14), 2=CPF (11), 3=CAEPF (14), 4=CNO (12), 5=CGC (14), 6=CEI (12)
    const config = {
        '1': { nome: 'CNPJ',  maxLen: 14, placeholder: '00.000.000/0001-00', mascara: mascarCNPJ,  valida: validaCNPJ  },
        '2': { nome: 'CPF',   maxLen: 11, placeholder: '000.000.000-00',     mascara: mascarCPF,   valida: validaCPF   },
        '3': { nome: 'CAEPF', maxLen: 14, placeholder: '000.000.000/000-00', mascara: mascarCAEPF, valida: validaLength(14) },
        '4': { nome: 'CNO',   maxLen: 12, placeholder: '00.000.00000/00',    mascara: mascarCNO,   valida: validaLength(12) },
        '5': { nome: 'CGC',   maxLen: 14, placeholder: '00.000.000/0001-00', mascara: mascarCNPJ,  valida: validaLength(14) },
        '6': { nome: 'CEI',   maxLen: 12, placeholder: '00.000.00000/00',    mascara: mascarCNO,   valida: validaLength(12) },
    };

    // ─── Máscaras ────────────────────────────────────────────
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
    function mascarCAEPF(v) {
        v = v.replace(/\D/g,'').slice(0,14);
        v = v.replace(/^(\d{3})(\d)/,'$1.$2');
        v = v.replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
        v = v.replace(/(\d{3})(\d{1,2})$/,'$1-$2');
        return v;
    }
    function mascarCNO(v) {
        v = v.replace(/\D/g,'').slice(0,12);
        v = v.replace(/^(\d{2})(\d)/,'$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/(\d{5})(\d)/,'$1/$2');
        return v;
    }

    // ─── Validações ──────────────────────────────────────────
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
    function validaLength(len) {
        return v => v.replace(/\D/g,'').length === len;
    }

    // ─── Atualizar UI ────────────────────────────────────────
    function atualizar() {
        const cfg = config[tipoContrib.value];
        label.textContent   = cfg.nome + ' *';
        input.placeholder   = cfg.placeholder;
        input.maxLength     = cfg.placeholder.length;

        // Reaplica máscara sobre o valor atual
        input.value = cfg.mascara(input.value);
        validar();
    }

    function validar() {
        const cfg   = config[tipoContrib.value];
        const raw   = input.value.replace(/\D/g,'');
        input.classList.remove('is-valid','is-invalid');
        feedback.className   = 'form-text';
        feedback.textContent = '';

        if (raw.length === 0) return;

        if (raw.length < cfg.maxLen) {
            input.classList.add('is-invalid');
            feedback.className = 'form-text text-danger';
            feedback.textContent = `${cfg.nome} incompleto (${raw.length}/${cfg.maxLen} dígitos)`;
        } else if (cfg.valida(input.value)) {
            input.classList.add('is-valid');
            feedback.className = 'form-text text-success';
            feedback.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${cfg.nome} válido`;
        } else {
            input.classList.add('is-invalid');
            feedback.className = 'form-text text-danger';
            feedback.innerHTML = `<i class="bi bi-x-circle-fill"></i> ${cfg.nome} inválido (dígito verificador não confere)`;
        }
    }

    tipoContrib.addEventListener('change', atualizar);

    input.addEventListener('input', function() {
        const cfg = config[tipoContrib.value];
        this.value = cfg.mascara(this.value);
        validar();
    });

    // Impedir submit se inválido (só bloqueia CPF e CNPJ que têm DV)
    document.getElementById('formContribuinte').addEventListener('submit', function(e) {
        const cfg   = config[tipoContrib.value];
        const raw   = input.value.replace(/\D/g,'');
        if (raw.length !== cfg.maxLen || !cfg.valida(input.value)) {
            e.preventDefault();
            input.focus();
            alert(`${cfg.nome} inválido. Verifique o número informado.`);
        }
    });

    // Inicializar
    atualizar();
})();
</script>