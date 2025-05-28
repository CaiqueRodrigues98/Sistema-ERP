-- Tabela de Cupons (atualizada)
CREATE TABLE IF NOT EXISTS cupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    desconto DECIMAL(5,2) NOT NULL, -- percentual
    validade DATE NOT NULL,
    valor_minimo DECIMAL(10,2) NOT NULL DEFAULT 0
);

-- Tabela de status de pedidos
CREATE TABLE IF NOT EXISTS pedido_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL
);

-- Adiciona coluna status_id na tabela de pedidos
ALTER TABLE pedidos ADD COLUMN status_id INT DEFAULT 1;
ALTER TABLE pedidos ADD CONSTRAINT fk_status FOREIGN KEY (status_id) REFERENCES pedido_status(id);
-- Script de criação do banco de dados para o mini ERP
CREATE DATABASE IF NOT EXISTS mini_erp;
USE mini_erp;

-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    estoque INT NOT NULL DEFAULT 0
);

-- Tabela de Cupons
CREATE TABLE IF NOT EXISTS cupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    desconto DECIMAL(5,2) NOT NULL, -- percentual
    validade DATE NOT NULL,
    valor_minimo DECIMAL(10,2) NOT NULL DEFAULT 0
);

-- Tabela de status de pedidos
CREATE TABLE IF NOT EXISTS pedido_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL
);

-- Adiciona coluna status_id na tabela de pedidos (executar apenas uma vez)
-- ALTER TABLE pedidos ADD COLUMN status_id INT DEFAULT 1;
-- ALTER TABLE pedidos ADD CONSTRAINT fk_status FOREIGN KEY (status_id) REFERENCES pedido_status(id);

-- Tabela de Pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    valor_total DECIMAL(10,2) NOT NULL,
    cupom_id INT,
    FOREIGN KEY (cupom_id) REFERENCES cupons(id)
);

-- Tabela de Itens do Pedido
CREATE TABLE IF NOT EXISTS pedido_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
