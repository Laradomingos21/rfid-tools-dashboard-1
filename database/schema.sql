-- =====================================================================
-- RFID TOOLS · Sistema de Monitoramento de Ferramentas
-- Schema do banco de dados (espelha o banco real "rfid_ferramentas")
-- =====================================================================
-- Como usar:
--   1. Rode este arquivo inteiro no phpMyAdmin (aba "Importar")
--      ou via terminal:  mysql -u root -p < database/schema.sql
--   2. Cadastre o primeiro funcionário pela tela "Cadastrar funcionário"
--      (a senha precisa ser gerada pelo PHP com password_hash).
--
-- Observações:
--   * Colunas, tipos, nulos e valores padrão foram copiados do banco real.
--   * As chaves estrangeiras (FOREIGN KEY) foram deduzidas das colunas
--     indexadas (*_id). Para conferir o DDL exato, rode no phpMyAdmin:
--         SHOW CREATE TABLE nome_da_tabela;
--   * Em "alertas" e "leitores" o NOT NULL de ferramenta_id / zona_id foi
--     deduzido do diagrama (colunas em negrito); defaults de resolvido,
--     ativa e criado_em foram assumidos seguindo o padrão das outras tabelas.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS rfid_ferramentas CHARACTER SET utf8mb4;
USE rfid_ferramentas;

-- ---------------------------------------------------------------------
-- Categorias das ferramentas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nome   VARCHAR(100) NOT NULL,
    icone  VARCHAR(100) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Zonas (locais onde as ferramentas ficam / circulam)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zonas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    descricao   TEXT         NULL,
    cor_hex     VARCHAR(7)   NULL,
    capacidade  INT          NULL,
    ativa       TINYINT(1)   NULL DEFAULT 1,
    criado_em   DATETIME     NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Funcionários (quem faz login e quem pega/devolve ferramentas)
-- Atenção: a coluna da senha se chama "Senha" (com S maiúsculo) e guarda
-- o hash gerado por password_hash(). Não há UNIQUE em email/matricula;
-- a duplicidade é checada no controller de cadastro.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcionarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    matricula  VARCHAR(50)  NULL,
    setor      VARCHAR(100) NULL,
    email      VARCHAR(150) NULL,
    ativo      TINYINT(1)   NULL DEFAULT 1,
    criado_em  DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    Senha      VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Ferramentas (o inventário monitorado por RFID)
-- status: disponivel | em_uso | perdida | manutencao
-- zona_atual_id: zona onde a ferramenta fica; funcionario_atual_id: quem
-- está com ela agora (NULL quando está disponível).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ferramentas (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id          INT          NULL,
    nome                  VARCHAR(100) NOT NULL,
    codigo                VARCHAR(50)  NULL,
    tag_rfid              VARCHAR(100) NULL,
    numero_serie          VARCHAR(100) NULL,
    fabricante            VARCHAR(100) NULL,
    zona_atual_id         INT          NULL,
    funcionario_atual_id  INT          NULL,
    status                ENUM('disponivel', 'em_uso', 'perdida', 'manutencao') NULL DEFAULT 'disponivel',
    proxima_manutencao    DATE         NULL,
    criado_em             DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ferr_categoria   (categoria_id),
    KEY idx_ferr_zona        (zona_atual_id),
    KEY idx_ferr_funcionario (funcionario_atual_id),
    CONSTRAINT fk_ferr_categoria   FOREIGN KEY (categoria_id)         REFERENCES categorias(id),
    CONSTRAINT fk_ferr_zona        FOREIGN KEY (zona_atual_id)        REFERENCES zonas(id),
    CONSTRAINT fk_ferr_funcionario FOREIGN KEY (funcionario_atual_id) REFERENCES funcionarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Movimentações (histórico de cada retirada/devolução/troca de zona)
-- Não existe coluna "tipo": o sistema grava 'emprestimo' ou 'devolucao'
-- na coluna observacao ao registrar a leitura de uma tag.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimentacoes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ferramenta_id    INT      NULL,
    zona_origem_id   INT      NULL,
    zona_destino_id  INT      NULL,
    funcionario_id   INT      NULL,
    observacao       TEXT     NULL,
    movido_em        DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mov_ferramenta   (ferramenta_id),
    KEY idx_mov_zona_origem  (zona_origem_id),
    KEY idx_mov_zona_destino (zona_destino_id),
    KEY idx_mov_funcionario  (funcionario_id),
    CONSTRAINT fk_mov_ferramenta   FOREIGN KEY (ferramenta_id)   REFERENCES ferramentas(id),
    CONSTRAINT fk_mov_zona_origem  FOREIGN KEY (zona_origem_id)  REFERENCES zonas(id),
    CONSTRAINT fk_mov_zona_destino FOREIGN KEY (zona_destino_id) REFERENCES zonas(id),
    CONSTRAINT fk_mov_funcionario  FOREIGN KEY (funcionario_id)  REFERENCES funcionarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Leitores RFID (antenas/leitores instalados em cada zona)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leitores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    zona_id     INT          NOT NULL,
    codigo      VARCHAR(100) NULL,
    descricao   TEXT         NULL,
    ip_address  VARCHAR(45)  NULL,
    porta       INT          NULL,
    ativa       TINYINT(1)   NULL DEFAULT 1,
    criado_em   DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_leitor_zona (zona_id),
    CONSTRAINT fk_leitor_zona FOREIGN KEY (zona_id) REFERENCES zonas(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Alertas (ocorrências ligadas a uma ferramenta)
-- resolvido: 0 = em aberto, 1 = resolvido (resolvido_em guarda quando)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alertas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ferramenta_id INT          NOT NULL,
    tipo          VARCHAR(50)  NULL,
    mensagem      TEXT         NULL,
    resolvido     TINYINT(1)   NULL DEFAULT 0,
    resolvido_em  DATETIME     NULL,
    criado_em     DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alerta_ferramenta (ferramenta_id),
    CONSTRAINT fk_alerta_ferramenta FOREIGN KEY (ferramenta_id) REFERENCES ferramentas(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Dados de exemplo (opcional — descomente para testar o painel)
-- ---------------------------------------------------------------------
-- INSERT INTO categorias (nome) VALUES ('Elétrica'), ('Manual'), ('Medição');
-- INSERT INTO zonas (nome) VALUES ('Almoxarifado A'), ('Almoxarifado B');
-- INSERT INTO ferramentas (categoria_id, nome, codigo, tag_rfid, zona_atual_id, status) VALUES
--   (1, 'Furadeira de Impacto Bosch', 'FRM-001', 'TAG-FRM-0001', 1, 'disponivel'),
--   (2, 'Chave de Torque 1/2"',       'FRM-002', 'TAG-FRM-0002', 1, 'disponivel'),
--   (3, 'Multímetro Digital',         'FRM-003', 'TAG-FRM-0003', 2, 'disponivel'),
--   (1, 'Esmerilhadeira Angular',     'FRM-004', 'TAG-FRM-0004', 1, 'manutencao');