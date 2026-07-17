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
        'max_import_rows' => 0, // 0 = sem limite
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

    /** Tabela 08 — Classificação Tributária (R-1000 classTrib) */
    'class_trib' => [
        '01' => '01 – Simples Nacional (previdenciária substituída)',
        '02' => '02 – Simples Nacional (previdenciária não substituída)',
        '03' => '03 – Simples Nacional (substituída e não substituída)',
        '04' => '04 – MEI',
        '06' => '06 – Agroindústria',
        '07' => '07 – Produtor Rural PJ',
        '08' => '08 – Consórcio Simplificado de Produtores Rurais',
        '09' => '09 – Órgão Gestor de Mão de Obra',
        '10' => '10 – Entidade Sindical (Lei 12.023/2009)',
        '11' => '11 – Associação Desportiva (clube de futebol profissional)',
        '13' => '13 – Banco / instituição financeira (art. 22 Lei 8.212/91)',
        '14' => '14 – Sindicatos em geral',
        '21' => '21 – Pessoa Física (exceto Segurado Especial)',
        '22' => '22 – Segurado Especial',
        '60' => '60 – Missão Diplomática / Repartição Consular',
        '70' => '70 – Empresa do Decreto 5.436/2005',
        '80' => '80 – Entidade Imune ou Isenta',
        '85' => '85 – Ente Federativo / Órgãos Públicos',
        '99' => '99 – Pessoas Jurídicas em Geral',
    ],

    /** softHouse opcional no R-1000 (preencha no .env se quiser informar) */
    'softhouse' => [
        'cnpj'     => env('REINF_SOFTHOUSE_CNPJ', ''),
        'razao'    => env('REINF_SOFTHOUSE_RAZAO', ''),
        'contato'  => env('REINF_SOFTHOUSE_CONTATO', ''),
        'telefone' => env('REINF_SOFTHOUSE_TELEFONE', ''),
        'email'    => env('REINF_SOFTHOUSE_EMAIL', ''),
    ],
];
