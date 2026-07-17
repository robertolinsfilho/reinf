<?php

return [
    'secret' => env('APP_SECRET', ''),

    'upload' => [
        'path' => storage_path('uploads'),
        'max_size' => 50 * 1024 * 1024,
        'allowed' => ['xlsx', 'xls', 'xlsm'],
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_sec' => 900,
        'max_import_rows' => 5000,
        'allow_simulated_transmission' => filter_var(
            env('ALLOW_SIMULATED_TRANSMISSION', '0'),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    'tp_amb' => (int) env('REINF_TP_AMB', 2),
    'ver_proc' => 'EFD-REINF-WEB-1.0',
    'proc_emi' => 1,
    'cert_path' => env('REINF_CERT_PATH', storage_path('certs')),
    'cert_pass' => env('REINF_CERT_PASS', ''),
    'ws_envio' => [
        1 => 'https://reinf.receita.economia.gov.br/recepcao/lotes/',
        2 => 'https://pre-reinf.receita.economia.gov.br/recepcao/lotes/',
    ],
    'ws_consulta' => [
        1 => 'https://reinf.receita.economia.gov.br/consulta/lotes/',
        2 => 'https://pre-reinf.receita.economia.gov.br/consulta/lotes/',
    ],
    'user_agent' => 'EFD-REINF-WEB/1.0',
];
