SET client_encoding = 'UTF8';

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    is_supplier BOOLEAN DEFAULT false NOT NULL,
    telefone VARCHAR(255) NOT NULL,
    cartaocredito VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT now()
);

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    fornecedor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    preco NUMERIC(10,2) NOT NULL CHECK (preco >= 0),
    estoque INTEGER DEFAULT 0 CHECK (estoque >= 0),
    categoria VARCHAR(100),
    foto_url TEXT,
    is_deleted BOOLEAN DEFAULT false NOT NULL,
    criado_em TIMESTAMP DEFAULT now()
);

CREATE TABLE IF NOT EXISTS pedidos (
    id SERIAL PRIMARY KEY,
    comprador_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    status VARCHAR(30) DEFAULT 'preparacao' NOT NULL 
        CHECK (status IN ('preparacao', 'transito', 'saiu', 'entregue')),
    data_estimada DATE,
    total NUMERIC(10,2) DEFAULT 0 NOT NULL,
    criado_em TIMESTAMP DEFAULT now()
);

CREATE TABLE IF NOT EXISTS itens_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE RESTRICT,
    quantidade INTEGER NOT NULL CHECK (quantidade > 0),
    preco_unit NUMERIC(10,2) NOT NULL,
    subtotal NUMERIC(10,2) GENERATED ALWAYS AS (quantidade * preco_unit) STORED
);

CREATE TABLE IF NOT EXISTS carrinho (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade INTEGER DEFAULT 1 NOT NULL CHECK (quantidade > 0),
    adicionado_em TIMESTAMP DEFAULT now() NOT NULL,
    UNIQUE(usuario_id, produto_id)
);


-- Dados iniciais

INSERT INTO usuarios (nome, email, senha, is_supplier, telefone, cartaocredito, criado_em) VALUES
('Douglas Biazus', 'douglasb50041@gmail.com', '$2y$10$cESMRlLYpfjTjnw/a/zTN.BItuhegzoS2En.PzDmgfzgPTeUbgzZi', true, '000', '000', '2026-04-30 18:39:09'),
('Comprador', 'comprador@gmail.com', '$2y$10$UEauhhqyII/aEw/mk.ER.eGZWQ8OYQW8RJs6lMyE/1pkaqFX/t9YC', false, '000', '000', '2026-04-30 18:39:44')
ON CONFLICT (email) DO NOTHING;

INSERT INTO produtos (id, fornecedor_id, nome, descricao, preco, estoque, categoria, criado_em) VALUES
(9, 1, 'Almofada', 'Teste de produto', 57.00, 15, 'Móveis', '2026-04-30 20:12:45')
ON CONFLICT (id) DO NOTHING;