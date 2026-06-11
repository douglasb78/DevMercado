SET client_encoding = 'UTF8';

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    is_supplier BOOLEAN DEFAULT false NOT NULL,
    is_admin BOOLEAN DEFAULT false NOT NULL,
    telefone VARCHAR(255) NOT NULL,
    cartaocredito VARCHAR(255) NOT NULL,
    endereco TEXT DEFAULT '' NOT NULL,
    criado_em TIMESTAMP DEFAULT now()
);

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT false NOT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS endereco TEXT DEFAULT '' NOT NULL;

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    fornecedor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    preco NUMERIC(10,2) NOT NULL CHECK (preco >= 0),
    estoque INTEGER DEFAULT 0 CHECK (estoque >= 0),
    categoria VARCHAR(100),
    foto_url TEXT,                    -- Foto do produto
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
INSERT INTO usuarios (nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em) VALUES
('Douglas Biazus', 'douglasb50041@gmail.com', '$2y$10$cESMRlLYpfjTjnw/a/zTN.BItuhegzoS2En.PzDmgfzgPTeUbgzZi', true, true, '000', '000', '', '2026-04-30 18:39:09'),
('Comprador', 'comprador@gmail.com', '$2y$10$UEauhhqyII/aEw/mk.ER.eGZWQ8OYQW8RJs6lMyE/1pkaqFX/t9YC', false, false, '000', '000', '', '2026-04-30 18:39:44')
ON CONFLICT (email) DO NOTHING;

-- Inserção do produto com foto_url
-- Inserção de 50 produtos de Hardware de PC
INSERT INTO produtos (id, fornecedor_id, nome, descricao, preco, estoque, categoria, foto_url, criado_em) 
VALUES
(9, 1, 'RTX 4060 Ti 8GB', 'Placa de vídeo NVIDIA GeForce RTX 4060 Ti 8GB GDDR6', 2899.90, 18, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(10, 1, 'RTX 4070 12GB', 'Placa de vídeo NVIDIA GeForce RTX 4070 12GB GDDR6X', 3799.90, 12, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(11, 1, 'RTX 4080 Super', 'Placa de vídeo NVIDIA GeForce RTX 4080 Super 16GB', 6799.90, 7, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(12, 1, 'RX 7600 8GB', 'Placa de vídeo AMD Radeon RX 7600 8GB GDDR6', 2199.90, 22, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(13, 1, 'RX 7800 XT 16GB', 'Placa de vídeo AMD Radeon RX 7800 XT 16GB GDDR6', 3299.90, 9, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),

(14, 1, 'Ryzen 7 7800X3D', 'Processador AMD Ryzen 7 7800X3D 8 núcleos 4.2GHz', 2899.90, 15, 'Processador', '', '2026-04-30 20:12:45'),
(15, 1, 'Ryzen 5 7600X', 'Processador AMD Ryzen 5 7600X 6 núcleos 4.7GHz', 1599.90, 25, 'Processador', '', '2026-04-30 20:12:45'),
(16, 1, 'Core i7-14700K', 'Processador Intel Core i7-14700K 20 núcleos', 2799.90, 11, 'Processador', '', '2026-04-30 20:12:45'),
(17, 1, 'Core i5-14600K', 'Processador Intel Core i5-14600K 14 núcleos', 1899.90, 20, 'Processador', '', '2026-04-30 20:12:45'),

(18, 1, 'B650 Aorus Elite AX', 'Placa-mãe Gigabyte B650 Aorus Elite AX (AM5)', 1399.90, 14, 'Placa-mãe', '', '2026-04-30 20:12:45'),
(19, 1, 'B550 Tomahawk MAX WiFi', 'Placa-mãe MSI B550 Tomahawk MAX WiFi', 899.90, 28, 'Placa-mãe', '', '2026-04-30 20:12:45'),
(20, 1, 'Z790 Gaming X', 'Placa-mãe Gigabyte Z790 Gaming X (Intel)', 1899.90, 8, 'Placa-mãe', '', '2026-04-30 20:12:45'),
(21, 1, 'B760M Mortar WiFi', 'Placa-mãe MSI B760M Mortar WiFi DDR5', 1199.90, 16, 'Placa-mãe', '', '2026-04-30 20:12:45'),

(22, 1, 'Kingston Fury Beast 32GB (2x16) 6000MHz', 'Kit Memória RAM DDR5 32GB 6000MHz CL36', 679.90, 35, 'Memória RAM', '', '2026-04-30 20:12:45'),
(23, 1, 'Corsair Vengeance 32GB (2x16) 6400MHz', 'Kit Memória RAM DDR5 32GB 6400MHz CL32', 749.90, 22, 'Memória RAM', '', '2026-04-30 20:12:45'),
(24, 1, 'Kingston Fury Beast 16GB 3200MHz', 'Memória RAM DDR4 16GB 3200MHz', 229.90, 40, 'Memória RAM', '', '2026-04-30 20:12:45'),

(25, 1, 'SSD NVMe Kingston KC3000 1TB', 'SSD NVMe 1TB PCIe 4.0 até 7000MB/s', 579.90, 30, 'Armazenamento', '', '2026-04-30 20:12:45'),
(26, 1, 'SSD WD Black SN850X 2TB', 'SSD NVMe 2TB PCIe 4.0 até 7300MB/s', 1099.90, 18, 'Armazenamento', '', '2026-04-30 20:12:45'),
(27, 1, 'SSD Crucial T700 1TB', 'SSD NVMe PCIe 5.0 1TB - Velocidade extrema', 899.90, 12, 'Armazenamento', '', '2026-04-30 20:12:45'),

(28, 1, 'Fonte Corsair RM850x 850W 80+ Gold', 'Fonte Modular 850W 80 Plus Gold Full Modular', 899.90, 17, 'Fonte', '', '2026-04-30 20:12:45'),
(29, 1, 'Fonte Gigabyte UD850GM 850W', 'Fonte 850W 80 Plus Gold', 649.90, 23, 'Fonte', '', '2026-04-30 20:12:45'),
(30, 1, 'Fonte Seasonic Focus GX-1000 1000W', 'Fonte Modular 1000W 80 Plus Gold', 1299.90, 9, 'Fonte', '', '2026-04-30 20:12:45'),

(31, 1, 'Gabinete Lian Li Lancool 216', 'Gabinete Mid Tower com 3 fans ARGB', 599.90, 19, 'Gabinete', '', '2026-04-30 20:12:45'),
(32, 1, 'Gabinete Corsair 4000D Airflow', 'Gabinete Mid Tower High Airflow', 749.90, 14, 'Gabinete', '', '2026-04-30 20:12:45'),
(33, 1, 'Gabinete NZXT H6 Flow', 'Gabinete Compacto com painel de vidro', 899.90, 11, 'Gabinete', '', '2026-04-30 20:12:45'),

(34, 1, 'Water Cooler Deepcool LS720 360mm', 'Water Cooler AIO 360mm ARGB', 899.90, 13, 'Refrigeração', '', '2026-04-30 20:12:45'),
(35, 1, 'Air Cooler Noctua NH-U12S', 'Cooler para CPU Noctua NH-U12S chromax.black', 449.90, 21, 'Refrigeração', '', '2026-04-30 20:12:45'),

(36, 1, 'RTX 4090 24GB', 'Placa de vídeo NVIDIA GeForce RTX 4090 24GB', 10999.90, 5, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(37, 1, 'Ryzen 9 7950X3D', 'Processador AMD Ryzen 9 7950X3D 16 núcleos', 4699.90, 8, 'Processador', '', '2026-04-30 20:12:45'),
(38, 1, 'Core i9-14900K', 'Processador Intel Core i9-14900K 24 núcleos', 3899.90, 10, 'Processador', '', '2026-04-30 20:12:45'),

(39, 1, 'X670E Aorus Master', 'Placa-mãe Gigabyte X670E Aorus Master', 2899.90, 6, 'Placa-mãe', '', '2026-04-30 20:12:45'),
(40, 1, 'Z790 Aorus Elite AX', 'Placa-mãe Gigabyte Z790 Aorus Elite AX', 1699.90, 12, 'Placa-mãe', '', '2026-04-30 20:12:45'),

(41, 1, 'Corsair Dominator Platinum 64GB (2x32)', 'Kit RAM DDR5 64GB 6400MHz CL32', 1599.90, 15, 'Memória RAM', '', '2026-04-30 20:12:45'),
(42, 1, 'Kingston Fury Beast 64GB (2x32) 6000MHz', 'Kit RAM DDR5 64GB 6000MHz', 1249.90, 20, 'Memória RAM', '', '2026-04-30 20:12:45'),

(43, 1, 'SSD Samsung 990 PRO 4TB', 'SSD NVMe 4TB PCIe 4.0 7450MB/s', 2199.90, 14, 'Armazenamento', '', '2026-04-30 20:12:45'),
(44, 1, 'SSD WD Black SN770 1TB', 'SSD NVMe 1TB PCIe 4.0', 429.90, 38, 'Armazenamento', '', '2026-04-30 20:12:45'),

(45, 1, 'Fonte Corsair HX1200i 1200W Platinum', 'Fonte 1200W 80 Plus Platinum Full Modular', 1899.90, 7, 'Fonte', '', '2026-04-30 20:12:45'),
(46, 1, 'Gabinete Fractal Design Meshify 2', 'Gabinete High Airflow', 1099.90, 10, 'Gabinete', '', '2026-04-30 20:12:45'),

(47, 1, 'Water Cooler Arctic Liquid Freezer III 420', 'Water Cooler AIO 420mm', 1299.90, 9, 'Refrigeração', '', '2026-04-30 20:12:45'),
(48, 1, 'Thermalright Peerless Assassin 120', 'Cooler Dual Tower 6 Heatpipes', 279.90, 32, 'Refrigeração', '', '2026-04-30 20:12:45'),

(49, 1, 'Monitor Gamer 27" 1440p 165Hz', 'Monitor IPS 2560x1440 165Hz 1ms', 1399.90, 16, 'Periféricos', '', '2026-04-30 20:12:45'),
(50, 1, 'Teclado Mecânico Redragon Kumara', 'Teclado Mecânico RGB Switch Brown', 289.90, 25, 'Periféricos', '', '2026-04-30 20:12:45'),

(51, 1, 'Mouse Logitech G Pro X Superlight', 'Mouse Gamer Wireless 63g', 599.90, 18, 'Periféricos', '', '2026-04-30 20:12:45'),
(52, 1, 'Headset HyperX Cloud Alpha', 'Headset Gamer com som 7.1', 449.90, 22, 'Periféricos', '', '2026-04-30 20:12:45'),

(53, 1, 'RTX 4050 6GB', 'Placa de vídeo NVIDIA GeForce RTX 4050 6GB', 1899.90, 20, 'Placa de Vídeo', '', '2026-04-30 20:12:45'),
(54, 1, 'Ryzen 5 7500F', 'Processador AMD Ryzen 5 7500F 6 núcleos', 999.90, 30, 'Processador', '', '2026-04-30 20:12:45'),
(55, 1, 'Aorus Gen5 10000 2TB', 'SSD NVMe PCIe 5.0 2TB', 1499.90, 13, 'Armazenamento', '', '2026-04-30 20:12:45'),
(56, 1, 'Fonte EVGA SuperNOVA 750 G6', 'Fonte 750W 80 Plus Gold Modular', 699.90, 24, 'Fonte', '', '2026-04-30 20:12:45'),
(57, 1, 'Gabinete Pichau Gaming', 'Gabinete com 4 fans ARGB', 399.90, 35, 'Gabinete', '', '2026-04-30 20:12:45'),
(58, 1, 'Cooler Deepcool AK400', 'Cooler Air para CPU 220W TDP', 219.90, 28, 'Refrigeração', '', '2026-04-30 20:12:45')
ON CONFLICT (id) DO NOTHING;
