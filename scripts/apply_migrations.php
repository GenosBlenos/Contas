<?php
// apply_migrations.php
// Run this script from PHP CLI to create minimal required tables.

// Use existing connection setup in app/conexao.php which defines $pdo (and $conn)
require_once __DIR__ . '/../src/includes/Database.php';
$pdo = Database::getInstance()->getConnection();



$statements = [
    "CREATE TABLE IF NOT EXISTS agua (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_ligacao VARCHAR(255),
        proprietario VARCHAR(255),
        endereco_ligacao VARCHAR(255),
        referencia VARCHAR(50),
        emissao DATE,
        vencimento DATE,
        consumo_m3 INT,
        total_a_pagar DECIMAL(10, 2),
        status VARCHAR(50) DEFAULT 'pendente',
        arquivo_pdf VARCHAR(255),
        observacoes TEXT
    );",
    "CREATE TABLE IF NOT EXISTS comunicacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_nota_fiscal VARCHAR(255),
        credor VARCHAR(255),
        cnpj_credor VARCHAR(20),
        data_emissao DATE,
        periodo_prestacao VARCHAR(255),
        valor_total DECIMAL(10, 2),
        status VARCHAR(50) DEFAULT 'pendente',
        arquivo_pdf VARCHAR(255),
        observacoes TEXT
    );",
    "CREATE TABLE IF NOT EXISTS fatura_veiculos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_fatura VARCHAR(255),
        codigo_cliente VARCHAR(255),
        data_emissao DATE,
        data_vencimento DATE,
        total_a_pagar DECIMAL(10, 2),
        status VARCHAR(50) DEFAULT 'pendente',
        arquivo_pdf VARCHAR(255),
        observacoes TEXT
    );",
    "CREATE TABLE IF NOT EXISTS energia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo_instalacao VARCHAR(255),
        conta_mes VARCHAR(50),
        vencimento DATE,
        total_a_pagar DECIMAL(10, 2),
        endereco_consumo VARCHAR(255),
        classificacao VARCHAR(255),
        consumo_kwh INT,
        status VARCHAR(50) DEFAULT 'pendente',
        arquivo_pdf VARCHAR(255),
        observacoes TEXT
    );",
    "ALTER TABLE energia ADD COLUMN fat_impostos DECIMAL(10, 2);",
    "ALTER TABLE energia ADD COLUMN fat_distribuidora DECIMAL(10, 2);",
    "ALTER TABLE energia ADD COLUMN multa_atraso DECIMAL(10, 2);",
    "ALTER TABLE energia ADD COLUMN imposto_retido_total DECIMAL(10, 2);",
    "ALTER TABLE energia ADD COLUMN imposto_retido_irrf DECIMAL(10, 2);",
    "CREATE TABLE IF NOT EXISTS telefone (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contrato VARCHAR(255),
        fatura VARCHAR(255),
        vencimento DATE,
        total_a_pagar DECIMAL(10, 2),
        periodo_servico VARCHAR(100),
        valor_servico DECIMAL(10, 2),
        status VARCHAR(50) DEFAULT 'pendente',
        arquivo_pdf VARCHAR(255),
        observacoes TEXT
    );",
    "CREATE TABLE IF NOT EXISTS unidades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        identificador_unidade VARCHAR(255),
        endereco VARCHAR(255),
        responsavel VARCHAR(255)
    );",
    "CREATE TABLE IF NOT EXISTS categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE
    );"
];

try {
    foreach ($statements as $sql) {
        echo "Executing...\n";
        $pdo->exec($sql);
        echo "OK\n";
    }

    echo "Migrations applied successfully.\n";
    exit(0);
} catch (PDOException $e) {
    echo "PDOException: " . $e->getMessage() . "\n";
    exit(2);
}

?>
