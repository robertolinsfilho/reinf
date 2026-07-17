@if(!empty($flash))
<div class="alert alert-{{ $flash['tipo'] === 'sucesso' || $flash['tipo'] === 'success' ? 'success' : 'danger' }} flash-alert">
    {{ $flash['mensagem'] }}
</div>
@endif

<div class="page-header">
    <h5><i class="bi bi-shield-lock me-2"></i>Certificado Digital A1</h5>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <!-- Upload -->
        <div class="card">
            <div class="card-header">Upload de Certificado (PFX / P12)</div>
            <div class="card-body p-4">
                <form action="/certificados/upload" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Contribuinte *</label>
                        @if(empty($contribuintes))
                            <div class="alert alert-warning py-2 small mb-0">
                                Cadastre um <a href="/contribuintes/novo">contribuinte</a> antes de enviar o certificado.
                            </div>
                        @else
                        <select name="contribuinte_id" class="form-select" required>
                            @foreach($contribuintes as $co)
                            <option value="{{ (int) $co['id'] }}">
                                {{ $co['razao_social'] }} — {{ $co['cnpj'] }}
                            </option>
                            @endforeach
                        </select>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Arquivo do Certificado *</label>
                        <input type="file" name="certificado" class="form-control" accept=".pfx,.p12" required {{ empty($contribuintes) ? 'disabled' : '' }}>
                        <div class="form-text">Certificado digital tipo A1, formato PFX ou P12.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha do Certificado *</label>
                        <input type="password" name="senha" class="form-control" required placeholder="Senha do arquivo PFX" {{ empty($contribuintes) ? 'disabled' : '' }}>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" {{ empty($contribuintes) ? 'disabled' : '' }}>
                        <i class="bi bi-upload me-1"></i> Enviar Certificado
                    </button>
                </form>
            </div>
        </div>

        <!-- Status -->
        @if(!empty($certAtivo))
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Certificado Ativo</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:120px">Titular</td>
                        <td class="fw-bold">{{ $certAtivo['titular'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">CNPJ</td>
                        <td class="font-monospace">{{ $certAtivo['cnpj'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Emissão</td>
                        <td>{{ $certAtivo['emissao'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Validade</td>
                        <td>
                            {{ $certAtivo['validade'] ?? '—' }}
                            @if(!empty($certAtivo['expirado']))
                                <span class="badge bg-danger ms-1">Expirado</span>
                            @elseif(($certAtivo['dias_rest'] ?? 999) <= 30)
                                <span class="badge bg-warning ms-1">{{ $certAtivo['dias_rest'] }} dias</span>
                            @else
                                <span class="badge bg-success ms-1">{{ $certAtivo['dias_rest'] }} dias</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            @if($certAtivo['valido'] ?? false)
                                <i class="bi bi-check-circle-fill text-success"></i> Válido
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i> Inválido
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        @else
        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Nenhum certificado ativo.</strong>
            Faça o upload do seu certificado A1 para habilitar a assinatura e transmissão de eventos.
            Sem certificado, o sistema opera em <strong>modo simulação</strong>.
        </div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Certificados Cadastrados</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Arquivo</th><th>Contribuinte</th><th>Titular</th><th>CNPJ</th><th>Validade</th><th>Status</th><th>Importado</th></tr>
                    </thead>
                    <tbody>
                        @if(empty($certificados))
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-shield-x display-6 d-block mb-2"></i>
                            Nenhum certificado cadastrado.
                        </td></tr>
                        @else
                        @foreach($certificados as $c)
                        <tr class="{{ $c['ativo'] ? 'table-success' : '' }}">
                            <td class="small">{{ $c['nome_arquivo'] ?? '' }}</td>
                            <td style="font-size:.82rem">{{ $c['razao_social'] ?? '—' }}</td>
                            <td style="font-size:.82rem">{{ $c['titular'] ?? '—' }}</td>
                            <td class="font-monospace small">{{ $c['cnpj_certificado'] ?? '—' }}</td>
                            <td class="small">{{ $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : '—' }}</td>
                            <td>
                                @if($c['ativo'])
                                    <span class="badge bg-success">Ativo</span>
                                @else
                                    <span class="badge bg-secondary">Inativo</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ date('d/m/Y H:i', strtotime($c['created_at'])) }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-question-circle me-2"></i>Sobre o Certificado Digital</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>O certificado digital A1 (arquivo PFX/P12) é necessário para <strong>assinar</strong> e <strong>transmitir</strong> eventos à Receita Federal.</li>
                    <li>Sem certificado, o sistema gera os XMLs normalmente, mas os envios operam em <strong>modo simulação</strong>.</li>
                    <li>Envie o certificado <strong>vinculado ao contribuinte</strong> (CNPJ) que fará a transmissão — a assinatura usa o cert daquele CNPJ.</li>
                    <li>Certificados A1 têm validade de 1 ano. O sistema alertará quando estiver próximo do vencimento.</li>
                    <li>A senha do PFX é validada no upload e fica <strong>armazenada criptografada</strong> (AES-256) para assinar/transmitir sem pedir de novo. Protegida pelo <code>APP_SECRET</code> do servidor.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
