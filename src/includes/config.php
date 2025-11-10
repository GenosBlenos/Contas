<?php

define('BASE_URL', '/compras/');

if (!defined('ML_API_URL')) {
    define('ML_API_URL', 'http://localhost:5001');
}

// Mapeamento de categorias para tabelas e colunas
$tableMapping = [
    'agua' => [
        'table' => 'agua',
        'columns' => [
            'numero_ligacao' => 'numero_ligacao',
            'proprietario' => 'proprietario',
            'endereco_ligacao' => 'endereco_ligacao',
            'referencia' => 'referencia',
            'emissao' => 'emissao',
            'vencimento' => 'vencimento',
            'consumo_m3' => 'consumo_m3',
            'total_a_pagar' => 'total_a_pagar'
        ]
    ],
    'comunicacao' => [
        'table' => 'comunicacao',
        'columns' => [
            'numero_nota_fiscal' => 'numero_nota_fiscal',
            'credor' => 'credor',
            'cnpj_credor' => 'cnpj_credor',
            'data_emissao' => 'data_emissao',
            'periodo_prestacao' => 'periodo_prestacao',
            'valor_total' => 'valor_total'
        ]
    ],
    'fatura_veiculos' => [
        'table' => 'fatura_veiculos',
        'columns' => [
            'numero_fatura' => 'numero_fatura',
            'codigo_cliente' => 'codigo_cliente',
            'data_emissao' => 'data_emissao',
            'data_vencimento' => 'data_vencimento',
            'total_a_pagar' => 'total_a_pagar'
        ]
    ],
    'energia' => [
        'table' => 'energia',
        'columns' => [
            'codigo_instalacao' => 'codigo_instalacao',
            'conta_mes' => 'conta_mes',
            'vencimento' => 'vencimento',
            'total_a_pagar' => 'total_a_pagar',
            'endereco_consumo' => 'endereco_consumo',
            'classificacao' => 'classificacao',
            'consumo_kwh' => 'consumo_kwh'
        ]
    ],
    'telefone' => [
        'table' => 'telefone',
        'columns' => [
            'contrato' => 'contrato',
            'fatura' => 'fatura',
            'vencimento' => 'vencimento',
            'total_a_pagar' => 'total_a_pagar',
            'periodo_servico' => 'periodo_servico',
            'valor_servico' => 'valor_servico'
        ]
    ]
];