<?php
// Carrega o autoload do Composer para gerenciar todas as dependências
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../app/conexao.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$module = $_GET['module'] ?? '';

if (!$module) {
    die("Módulo não informado.");
} 

$sql = '';
$referencia_column = '';

switch ($module) {
    case 'agua':
        $sql = "SELECT 
                    COALESCE(NULLIF(u.nome, ''), 'Sem Unidade') AS Unidade,
                    a.id AS ID,
                    a.numero_ligacao AS Numero_Ligacao,
                    a.endereco_ligacao AS Endereco,
                    a.referencia AS Referencia,
                    DATE_FORMAT(a.emissao, '%d/%m/%Y') AS Data_Emissao,
                    DATE_FORMAT(a.vencimento, '%d/%m/%Y') AS Data_Vencimento,
                    COALESCE(a.consumo_m3, 0) AS Consumo_m3,
                    a.total_a_pagar AS Valor_Total,
                    a.status AS Status,
                    a.arquivo_pdf AS Arquivo
                FROM agua a 
                LEFT JOIN unidades u ON a.unidade_id = u.id";
        $referencia_column = 'a.referencia';
        break;
    case 'energia':
        $sql = "SELECT 
                    COALESCE(NULLIF(u.nome, ''), 'Sem Unidade') AS Unidade,
                    e.id AS ID,
                    e.codigo_instalacao AS Instalacao,
                    e.conta_mes AS Referencia,
                    DATE_FORMAT(e.vencimento, '%d/%m/%Y') AS Vencimento,
                    e.total_a_pagar AS Valor,
                    e.endereco_consumo AS Endereco,
                    e.classificacao AS Classificacao,
                    COALESCE(e.consumo_kwh, 0) AS Consumo_kwh,
                    e.status AS Status,
                    e.arquivo_pdf AS Arquivo,
                    COALESCE(e.fat_impostos, 0) AS Impostos,
                    COALESCE(e.fat_distribuidora, 0) AS Distribuidora,
                    COALESCE(e.multa_atraso, 0) AS Multa,
                    COALESCE(e.imposto_retido_total, 0) AS Imposto_Retido,
                    COALESCE(e.imposto_retido_irrf, 0) AS IRRF,
                    e.valor_final AS Valor_Ajustado
                FROM energia e 
                LEFT JOIN unidades u ON e.unidade_id = u.id";
        $referencia_column = 'e.conta_mes';
        break;
    case 'telefone':
        $sql = "SELECT 
                    COALESCE(NULLIF(u.nome, ''), 'Sem Unidade') AS Unidade,
                    t.id AS ID,
                    t.contrato AS Contrato,
                    t.fatura AS Fatura,
                    t.periodo_servico AS Referencia,
                    DATE_FORMAT(t.vencimento, '%d/%m/%Y') AS Data_Vencimento,
                    t.total_a_pagar AS Valor_Total,
                    COALESCE(t.valor_servico, 0) AS Valor_Servico,
                    t.status AS Status,
                    t.arquivo_pdf AS Arquivo
                FROM telefone t 
                LEFT JOIN unidades u ON t.unidade_id = u.id";
        $referencia_column = 't.periodo_servico';
        break;
    case 'semparar':
        $sql = "SELECT 
                    COALESCE(NULLIF(u.nome, ''), 'Sem Unidade') AS Unidade,
                    s.id AS ID,
                    s.numero_fatura AS Numero_Fatura,
                    s.codigo_cliente AS Codigo_Cliente,
                    DATE_FORMAT(s.data_emissao, '%d/%m/%Y') AS Data_Emissao,
                    DATE_FORMAT(s.data_vencimento, '%d/%m/%Y') AS Data_Vencimento,
                    s.total_a_pagar AS Valor_Total,
                    s.status AS Status,
                    s.arquivo_pdf AS Arquivo
                FROM semparar s 
                LEFT JOIN unidades u ON s.unidade_id = u.id";
        $referencia_column = 's.data_emissao';
        break;
    case 'internet':
        $sql = "SELECT 
                    COALESCE(NULLIF(u.nome, ''), 'Sem Unidade') AS Unidade,
                    i.id AS ID,
                    i.numero_nota_fiscal AS Numero_Nota_Fiscal,
                    i.credor AS Credor,
                    i.cnpj_credor AS CNPJ_Credor,
                    DATE_FORMAT(i.data_emissao, '%d/%m/%Y') AS Data_Emissao,
                    i.periodo_prestacao AS Periodo_Prestacao,
                    i.valor_total AS Valor_Total,
                    i.status AS Status,
                    i.arquivo_pdf AS Arquivo
                FROM internet i 
                LEFT JOIN unidades u ON i.unidade_id = u.id";
        $referencia_column = 'i.periodo_prestacao';
        break;
    default:
        die("Módulo inválido.");
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dados)) {
        die("Nenhum dado encontrado para o módulo selecionado.");
    }

    // Obtém a referência (mês/ano) do primeiro registro
    $primeira_referencia = $dados[0]['Referencia'] ?? '';
    
    // Converte a referência para o formato desejado (MES-ANO)
    $mes_ano = '';
    if ($primeira_referencia) {
        // Tenta detectar o formato da referência e converter
        if (preg_match('/([A-Z]{3})\/(\d{4})/', $primeira_referencia, $matches)) {
            // Formato: AGO/2025 -> AGO-2025
            $mes_ano = $matches[1] . '-' . $matches[2];
        } elseif (preg_match('/(\d{2})\/(\d{4})/', $primeira_referencia, $matches)) {
            // Formato: 08/2025 -> AGO-2025
            $meses = [
                '01' => 'JAN', '02' => 'FEV', '03' => 'MAR', '04' => 'ABR',
                '05' => 'MAI', '06' => 'JUN', '07' => 'JUL', '08' => 'AGO',
                '09' => 'SET', '10' => 'OUT', '11' => 'NOV', '12' => 'DEZ'
            ];
            $mes_ano = $meses[$matches[1]] . '-' . $matches[2];
        } else {
            // Usa o mês e ano atual como fallback
            $mes_ano = strtoupper(date('M-Y'));
        }
    } else {
        // Fallback para data atual
        $mes_ano = strtoupper(date('M-Y'));
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => [
            'name' => 'Arial',
            'size' => 12,
            'bold' => true,
            'uppercase' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '404040'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THICK,
                'color' => ['rgb' => '000000'],
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    // Estilo para as células de dados
    $dataStyle = [
        'font' => [
            'name' => 'Arial',
            'size' => 10,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    // Estilo para centralização horizontal
    $centerStyle = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
    ];

    // Define cabeçalhos
    $headers = array_keys($dados[0]);
    $col = 'A';
    
    // Mapeamento de tipos de coluna para formatação
    $moneyColumns = ['Valor_Total', 'Valor', 'Impostos', 'Distribuidora', 'Multa', 'Imposto_Retido', 'IRRF', 'Valor_Ajustado', 'Valor_Servico'];
    $centerColumns = ['Classificacao', 'Status', 'Referencia'];
    $numberColumns = ['Instalacao', 'Consumo_m3', 'Consumo_kwh'];
    
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }

    // Aplica estilo ao cabeçalho
    $lastHeaderCol = chr(ord('A') + count($headers) - 1);
    $sheet->getStyle('A1:' . $lastHeaderCol . '1')->applyFromArray($headerStyle);

    // Preenche os dados
    $row = 2;
    foreach ($dados as $registro) {
        $col = 'A';
        $colIndex = 0;
        
        foreach ($registro as $valor) {
            $headerName = $headers[$colIndex];
            $cellCoordinate = $col . $row;
            
            // Formatação específica por tipo de coluna
            if (in_array($headerName, $moneyColumns) && is_numeric($valor)) {
                // Formata como moeda
                $sheet->setCellValue($cellCoordinate, $valor);
                $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('"R$"#,##0.00');
            } elseif ($headerName === 'Instalacao') {
                // Formata como número sem casas decimais
                $sheet->setCellValueExplicit($cellCoordinate, $valor, DataType::TYPE_NUMERIC);
                $sheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('0');
            } elseif (in_array($headerName, $numberColumns) && is_numeric($valor)) {
                // Formata como número
                $sheet->setCellValue($cellCoordinate, $valor);
            } elseif (in_array($headerName, $centerColumns)) {
                // Centraliza o texto
                $sheet->setCellValue($cellCoordinate, $valor);
                $sheet->getStyle($cellCoordinate)->applyFromArray($centerStyle);
            } else {
                $sheet->setCellValue($cellCoordinate, $valor);
            }
            
            $col++;
            $colIndex++;
        }
        $row++;
    }

    // Aplica estilo de borda aos dados
    $lastColumn = $sheet->getHighestDataColumn();
    $lastRow = $sheet->getHighestDataRow();
    $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray($dataStyle);

    // Aplica centralização para colunas específicas em todo o range
    foreach ($centerColumns as $colName) {
        $colIndex = array_search($colName, $headers);
        if ($colIndex !== false) {
            $colLetter = chr(ord('A') + $colIndex);
            $sheet->getStyle($colLetter . '2:' . $colLetter . $lastRow)->applyFromArray($centerStyle);
        }
    }

    // Aplica formatação de moeda para colunas específicas em todo o range
    foreach ($moneyColumns as $colName) {
        $colIndex = array_search($colName, $headers);
        if ($colIndex !== false) {
            $colLetter = chr(ord('A') + $colIndex);
            $sheet->getStyle($colLetter . '2:' . $colLetter . $lastRow)
                  ->getNumberFormat()
                  ->setFormatCode('"R$"#,##0.00');
        }
    }

    // Congela o cabeçalho
    $sheet->freezePane('A2');

    // Nome do arquivo com a lógica desejada
    $nomeModulo = ucfirst($module);
    $nomeArquivo = "Relatorio-{$nomeModulo}-{$mes_ano}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Erro no banco de dados: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    die("Erro ao gerar o arquivo XLSX: " . $e->getMessage());
}