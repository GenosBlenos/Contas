CREATE TABLE agua (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_ligacao VARCHAR(255),
    unidade VARCHAR(255),
    endereco_ligacao VARCHAR(255),
    referencia VARCHAR(50),
    emissao DATE,
    vencimento DATE,
    consumo_m3 INT,
    total_a_pagar DECIMAL(10, 2),
    arquivo_pdf VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',
    unidade_id INT FOREIGN KEY (unidade_id) REFERENCES unidades(id),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- CREATE TABLE internet (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     numero_nota_fiscal VARCHAR(255),
--     credor VARCHAR(255),
--     cnpj_credor VARCHAR(20),
--     data_emissao DATE,
--     periodo_prestacao VARCHAR(255),
--     valor_total DECIMAL(10, 2)
-- );

CREATE TABLE semparar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_fatura VARCHAR(255),
    codigo_cliente VARCHAR(255),
    data_emissao DATE,
    data_vencimento DATE,
    total_a_pagar DECIMAL(10, 2),
    arquivo_pdf VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',    
    unidade_id INT FOREIGN KEY (unidade_id) REFERENCES unidades(id),
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE energia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_instalacao VARCHAR(255),
    conta_mes VARCHAR(50),
    vencimento DATE,
    total_a_pagar DECIMAL(10, 2),
    endereco_consumo VARCHAR(255),
    classificacao VARCHAR(255),
    consumo_kwh INT,
    arquivo_pdf VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',
    fat_impostos DECIMAL(10, 2),
    fat_distribuidora DECIMAL(10, 2),
    multa_atraso DECIMAL(10, 2),
    imposto_retido_total DECIMAL(10, 2),
    imposto_retido_irrf DECIMAL(10, 2),
    valor_final DECIMAL(10, 2),
    unidade_id INT FOREIGN KEY (unidade_id) REFERENCES unidades(id),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE telefone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato VARCHAR(255),
    fatura VARCHAR(255),
    vencimento DATE,
    total_a_pagar DECIMAL(10, 2),
    periodo_servico VARCHAR(100),
    valor_servico DECIMAL(10, 2),
    arquivo_pdf VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',
    unidade_id INT FOREIGN KEY (unidade_id) REFERENCES unidades(id),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


