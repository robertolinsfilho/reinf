<div class="page-header">
    <h5><i class="bi bi-file-code me-2"></i>Schemas XSD Instalados</h5>
    <a href="/gerar" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Os arquivos XSD são publicados pela Receita Federal e usados para validar os XMLs gerados antes da transmissão.
    Coloque os arquivos em <code>storage/xsd/</code> com os nomes indicados abaixo.
    <br><br>
    <a href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/leiautes-esocial-v-s-1-3/efd-reinf"
       target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-1"></i> Baixar XSDs oficiais da Receita Federal
    </a>
</div>

<div class="card">
    <div class="card-header">Status dos XSDs (versão v2_01_02)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Nome do Arquivo Esperado</th>
                    <th>Status</th>
                    <th class="text-end">Tamanho</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status as $evento => $info): ?>
                <tr>
                    <td><span class="badge bg-primary"><?= $evento ?></span></td>
                    <td class="font-monospace small">storage/xsd/<?= $info['nome_arquivo'] ?></td>
                    <td>
                        <?php if ($info['instalado']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Instalado</span>
                        <?php else: ?>
                            <span class="badge bg-warning"><i class="bi bi-exclamation-triangle"></i> Não instalado</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end small text-muted">
                        <?= $info['instalado'] ? number_format($info['tamanho'] / 1024, 1) . ' KB' : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-question-circle me-2"></i>Como funciona</div>
    <div class="card-body small text-muted">
        <ul class="mb-0 ps-3">
            <li>Após gerar um XML, o sistema valida automaticamente contra o XSD do evento.</li>
            <li>Se o XSD <strong>não estiver instalado</strong>, a validação é ignorada (XML é salvo normalmente).</li>
            <li>Se o XML <strong>falhar na validação</strong>, o sistema mostra os erros e impede salvar até você corrigir (ou forçar).</li>
            <li>Recomendado instalar todos os XSDs antes de transmitir em produção.</li>
        </ul>
    </div>
</div>