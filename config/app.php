<?php

return [
    'app' => [
        'name'    => $_ENV['APP_NAME'] ?? 'STHEPSON',
        'url'     => $_ENV['APP_URL'] ?? 'http://localhost',
        'env'     => $_ENV['APP_ENV'] ?? 'production',
        'secret'  => $_ENV['APP_SECRET'] ?? '',
    ],
    'db' => [
        'host'    => $_ENV['DB_HOST'] ?? 'db',
        'port'    => $_ENV['DB_PORT'] ?? '3306',
        'name'    => $_ENV['DB_NAME'] ?? 'efd_reinf',
        'user'    => $_ENV['DB_USER'] ?? 'reinf_user',
        'pass'    => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'path'     => __DIR__ . '/../storage/uploads/',
        'max_size' => 50 * 1024 * 1024,
        'allowed'  => ['xlsx', 'xls', 'xlsm'],
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_sec'  => 900,
        'max_import_rows'    => 5000,
        'allow_simulated_transmission' => filter_var(
            $_ENV['ALLOW_SIMULATED_TRANSMISSION'] ?? '0',
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'reinf' => [
        'tp_amb'     => (int) ($_ENV['REINF_TP_AMB'] ?? 2), // 1=Produção, 2=Homologação
        'ver_proc'   => 'EFD-REINF-WEB-1.0',
        'proc_emi'   => 1, // 1=Aplicativo do contribuinte
        'cert_path'  => $_ENV['REINF_CERT_PATH'] ?? __DIR__ . '/../storage/certs/',
        'cert_pass'  => $_ENV['REINF_CERT_PASS'] ?? '',
        'ws_envio' => [
            1 => 'https://reinf.receita.economia.gov.br/recepcao/lotes/',
            2 => 'https://pre-reinf.receita.economia.gov.br/recepcao/lotes/',
        ],
        'ws_consulta' => [
            1 => 'https://reinf.receita.economia.gov.br/consulta/lotes/',
            2 => 'https://pre-reinf.receita.economia.gov.br/consulta/lotes/',
        ],
        'user_agent' => 'EFD-REINF-WEB/1.0',
    ],
];
