-- BANCO DE DADOS: e_sgp (Sistema de Gestão de Patrimônio)
-- SQL padrão ANSI: não depende de comandos MySQL como USE, AUTO_INCREMENT ou ENUM.
CREATE DATABASE IF NOT EXISTS e_sgp;
USE e_sgp;

-- Tabela para tipos de usuário (admin, gestor, usuario)
CREATE TABLE tipo_usuario (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL UNIQUE,
    descricao VARCHAR(255)
);

-- Tipos fixos de usuário do sistema
INSERT INTO tipo_usuario (nome, descricao) VALUES
('admin', 'Administrador da empresa, acesso total'),
('gestor', 'Gestor da prefeitura, cria usuários comuns e estruturas'),
('usuario', 'Usuário comum, cadastra itens');

-- Tabela para tipos de material (permanente ou consumo)
CREATE TABLE tipo_material (
    id INTEGER AUTO_INCREMENT,
    nome VARCHAR(20) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    CONSTRAINT pk_tipo_material PRIMARY KEY (id)
);

-- Tipos fixos de material
INSERT INTO tipo_material (nome, descricao) VALUES
('permanente', 'Bens duráveis a inventariar'),
('consumo', 'Materiais consumíveis não inventariados');

-- Tabela para gerenciar o usuário do sistema
CREATE TABLE usuario (
    id INT AUTO_INCREMENT, -- Antes: INT AUTO_INCREMENT
    nome VARCHAR(50) NOT NULL,
    email VARCHAR(64) NOT NULL UNIQUE,
    senha VARCHAR(32) NOT NULL,
    tipoUsuarioId INTEGER NOT NULL, -- Antes: nivel
    CONSTRAINT pk_usuario PRIMARY KEY (id),
    CONSTRAINT fk_usuario_tipo FOREIGN KEY (tipoUsuarioId)
        REFERENCES tipo_usuario(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);
INSERT INTO usuario (nome, email, senha, tipoUsuarioId) VALUES ('Admin','admin@gmail.com','admin123',1);

-- Tabela para responsáveis físicos ou servidores do patrimônio
CREATE TABLE responsavel (
    id INT AUTO_INCREMENT,
    usuarioId INTEGER,
    nome VARCHAR(50) NOT NULL,
    cargo VARCHAR(100),
    CONSTRAINT pk_responsavel PRIMARY KEY (id),
    CONSTRAINT fk_responsavel_usuario FOREIGN KEY (usuarioId)
        REFERENCES usuario(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

-- Tabela da secretaria municipal
CREATE TABLE secretaria (
    id INT AUTO_INCREMENT, -- Antes: INT AUTO_INCREMENT 
    nome VARCHAR(50) NOT NULL,
    descricao VARCHAR(255),
    responsavelId INTEGER, -- Antes: responsavel VARCHAR(50)
    CONSTRAINT pk_secretaria PRIMARY KEY (id),
    CONSTRAINT fk_secretaria_responsavel FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

-- Tabela da unidade vinculada à secretaria
CREATE TABLE unidade (
    id INT AUTO_INCREMENT, -- Antes: INT AUTO_INCREMENT
    nome VARCHAR(50) NOT NULL,
    secretariaId INTEGER NOT NULL,
    endereco VARCHAR(320),
    responsavelId INTEGER, -- Antes: responsavel VARCHAR(50)
    CONSTRAINT pk_unidade PRIMARY KEY (id),
    CONSTRAINT fk_unidade_secretaria FOREIGN KEY (secretariaId)
        REFERENCES secretaria(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT, -- Impede a exclusão de uma secretaria que tenha unidade vinculada
    CONSTRAINT fk_unidade_responsavel FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

-- Tabela do departamento dentro da unidade
CREATE TABLE departamento (
    id INT AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    unidadeId INTEGER NOT NULL, -- Antes: unidadeId
    responsavelId INTEGER, -- Antes: responsavelId referenciava usuario
    CONSTRAINT pk_departamento PRIMARY KEY (id),
    CONSTRAINT fk_departamento_unidade FOREIGN KEY (unidadeId)
        REFERENCES unidade(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT fk_departamento_responsavel FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

-- Tabela de localização física dentro do departamento
CREATE TABLE localizacao (
    id INT AUTO_INCREMENT,
    departamentoId INTEGER NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    CONSTRAINT pk_localizacao PRIMARY KEY (id),
    CONSTRAINT fk_localizacao_departamento FOREIGN KEY (departamentoId)
        REFERENCES departamento(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
);

-- Tabela principal do item de patrimônio
CREATE TABLE item (
    id INT AUTO_INCREMENT, -- Antes: INT AUTO_INCREMENT
    numeroPatrimonio VARCHAR(20) NOT NULL UNIQUE,
    descricao VARCHAR(255) NOT NULL,
    marca VARCHAR(50),
    modelo VARCHAR(50),
    numeroSerie VARCHAR(50),
    estado VARCHAR(15) NOT NULL DEFAULT 'bom' -- Antes: ENUM('otimo', 'bom', 'regular', 'ruim', 'inservivel') DEFAULT 'bom'
        CHECK (estado IN ('otimo', 'bom', 'ruim', 'péssimo')), -- Ajustado para refletir os estados mencionados na descrição do projeto
    dataAquisicao DATE,
    valor DECIMAL(10,2),
    notaFiscal VARCHAR(50),
    foto VARCHAR(255),
    observacoes VARCHAR(255),
    departamentoId INTEGER NOT NULL, -- referencia o departamento onde o item está alocado
    localizacaoId INTEGER, -- Localização física opcional dentro do departamento
    tipoMaterialId INTEGER NOT NULL, -- Classificação do bem (permanente ou consumo)
    responsavelId INTEGER, -- Agora referencia responsavel, não necessariamente usuário do sistema
    CONSTRAINT pk_item PRIMARY KEY (id),
    CONSTRAINT fk_item_departamento FOREIGN KEY (departamentoId)
        REFERENCES departamento(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT fk_item_localizacao FOREIGN KEY (localizacaoId)
        REFERENCES localizacao(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT fk_item_tipo_material FOREIGN KEY (tipoMaterialId)
        REFERENCES tipo_material(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_item_responsavel FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL -- Alterado para usar responsavel separado
);

-- Tabela para histórico de movimentação do bem
CREATE TABLE movimentacao (
    id INT AUTO_INCREMENT, -- Antes: INT AUTO_INCREMENT
    itemId INTEGER NOT NULL,
    tipo VARCHAR(20) NOT NULL -- Antes: ENUM('criacao', 'transferencia', 'baixa', 'manutencao')
        CHECK (tipo IN ('criacao', 'transferencia', 'baixa', 'manutencao')),
    departamentoOrigemId INTEGER,
    departamentoDestinoId INTEGER,
    observacao VARCHAR(255),
    dataMovimentacao DATE NOT NULL,
    CONSTRAINT pk_movimentacao PRIMARY KEY (id),
    CONSTRAINT fk_mov_item FOREIGN KEY (itemId)
        REFERENCES item(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT fk_mov_origem FOREIGN KEY (departamentoOrigemId)
        REFERENCES departamento(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT fk_mov_destino FOREIGN KEY (departamentoDestinoId)
        REFERENCES departamento(id) -- Antes: REFERENCES locais(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

-- Tabela para termos de responsabilidade de bens
CREATE TABLE termo_responsabilidade (
    id INT AUTO_INCREMENT,
    responsavelId INTEGER NOT NULL,
    itemId INTEGER NOT NULL,
    dataEmissao DATE NOT NULL,
    descricao VARCHAR(1000),
    CONSTRAINT pk_termo_responsabilidade PRIMARY KEY (id),
    CONSTRAINT fk_termo_responsabilidade_responsavel FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_termo_responsabilidade_item FOREIGN KEY (itemId)
        REFERENCES item(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

-- Tabela para registro de inventários anuais
CREATE TABLE inventario (
    id INT AUTO_INCREMENT,
    ano INTEGER NOT NULL,
    nome VARCHAR(100),
    dataInicio DATE,
    dataFim DATE,
    status VARCHAR(20) NOT NULL CHECK (status IN ('ABERTO','CONCLUIDO','SUSPENSO')),
    unidadeId INTEGER,
    CONSTRAINT pk_inventario PRIMARY KEY (id),
    CONSTRAINT fk_inventario_unidade FOREIGN KEY (unidadeId)
        REFERENCES unidade(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
);

CREATE TABLE inventario_item (
    id INT AUTO_INCREMENT,
    inventarioId INTEGER NOT NULL,
    itemId INTEGER NOT NULL,
    localizado BOOLEAN NOT NULL DEFAULT TRUE,
    estado VARCHAR(15) NOT NULL CHECK (estado IN ('otimo', 'bom', 'ruim', 'péssimo')),
    observacao VARCHAR(255),
    CONSTRAINT pk_inventario_item PRIMARY KEY (id),
    CONSTRAINT fk_inventario_item_inventario FOREIGN KEY (inventarioId)
        REFERENCES inventario(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT fk_inventario_item_item FOREIGN KEY (itemId)
        REFERENCES item(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
);

-- Tabela para registro de baixas de bens
CREATE TABLE baixa (
    id INT AUTO_INCREMENT,
    itemId INTEGER NOT NULL,
    tipo VARCHAR(30) NOT NULL CHECK (tipo IN ('DOACAO','INUTILIZACAO','PERDIDO','CADASTRAMENTO_INDEVIDO')),
    dataBaixa DATE NOT NULL,
    justificativa VARCHAR(1000),
    documento VARCHAR(255),
    CONSTRAINT pk_baixa PRIMARY KEY (id),
    CONSTRAINT fk_baixa_item FOREIGN KEY (itemId)
        REFERENCES item(id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
);

-- Tabela para histórico de ações básicas do sistema
CREATE TABLE historico (
    id INT AUTO_INCREMENT,
    usuarioId INTEGER NOT NULL,
    acao VARCHAR(20) NOT NULL CHECK (acao IN ('CRIAR','ALTERAR','DELETAR')),
    tabelaAlvo VARCHAR(50) NOT NULL,
    registroId INTEGER, -- ID do registro afetado, se criou uma secretaria, aqui vai o ID da secretaria, se criou um item, aqui vai o ID do item, etc.
    descricao VARCHAR(255),
    dataRegistro DATE NOT NULL,
    CONSTRAINT pk_historico PRIMARY KEY (id),
    CONSTRAINT fk_historico_usuario FOREIGN KEY (usuarioId)
        REFERENCES usuario(id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
);

-- Exemplo de uso da tabela de histórico:
-- INSERT INTO historico (usuarioId, acao, tabelaAlvo, registroId, descricao)
-- VALUES (1, 'CRIAR', 'secretaria', 10, 'Criou nova secretaria Municipal');

