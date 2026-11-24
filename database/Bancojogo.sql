CREATE DATABASE IF NOT EXISTS Bancojogo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE Bancojogo;

CREATE TABLE IF NOT EXISTS usuarios
(
    id_usuario INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    CONSTRAINT UQ_usuarios_email UNIQUE (email)
);
CREATE TABLE IF NOT EXISTS perfis
(
    id_perfil INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    nome_perfil VARCHAR(50) NOT NULL DEFAULT 'guest',
    genero VARCHAR(12) NOT NULL DEFAULT 'masculino',
    pokedollar INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_perfil),
    CONSTRAINT FK_usuarios_TO_perfis
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
);

CREATE TABLE IF NOT EXISTS categorias_itens
(
    id_categoria INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_categoria),
    CONSTRAINT UQ_categorias_itens_nome UNIQUE (nome)
);

CREATE TABLE IF NOT EXISTS itens
(
    id_item INT NOT NULL AUTO_INCREMENT,
    id_categoria INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    preco INT NOT NULL,
    valor_hp INT NULL DEFAULT 0,
    cura_status BOOLEAN NULL DEFAULT FALSE,
    revive BOOLEAN NULL DEFAULT FALSE,
    evolucao BOOLEAN NULL DEFAULT FALSE,
    PRIMARY KEY (id_item),
    CONSTRAINT FK_categorias_itens_TO_itens
        FOREIGN KEY (id_categoria) REFERENCES categorias_itens (id_categoria)
);

CREATE TABLE IF NOT EXISTS compras
(
    id_compra INT NOT NULL AUTO_INCREMENT,
    id_perfil INT NOT NULL,
    id_item INT NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario INT NOT NULL,
    valor_total INT NOT NULL,
    comprado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_compra),
    CONSTRAINT FK_perfis_TO_compras
        FOREIGN KEY (id_perfil) REFERENCES perfis (id_perfil),
    CONSTRAINT FK_itens_TO_compras
        FOREIGN KEY (id_item) REFERENCES itens (id_item)
);

CREATE TABLE IF NOT EXISTS inventarios
(
    id_perfil INT NOT NULL,
    id_item INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_perfil, id_item),
    CONSTRAINT FK_perfis_TO_inventarios
        FOREIGN KEY (id_perfil) REFERENCES perfis (id_perfil),
    CONSTRAINT FK_itens_TO_inventarios
        FOREIGN KEY (id_item) REFERENCES itens (id_item)
);

CREATE TABLE IF NOT EXISTS pokedex
(
    id_pokedex INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    HP INT NOT NULL,
    ATQ INT NOT NULL,
    ATQE INT NOT NULL,
    DEF INT NOT NULL,
    DEFE INT NOT NULL,
    SPE INT NOT NULL,
    PRIMARY KEY (id_pokedex)
);

CREATE TABLE IF NOT EXISTS tipos
(
    id_tipo INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(30) NOT NULL,
    PRIMARY KEY (id_tipo),
    CONSTRAINT UQ_tipos_nome UNIQUE (nome)
);

CREATE TABLE IF NOT EXISTS pokemons
(
    id_pokemon INT NOT NULL AUTO_INCREMENT,
    id_pokedex INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    status VARCHAR(30) NULL DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pokemon),
    CONSTRAINT FK_pokedex_TO_pokemons
        FOREIGN KEY (id_pokedex) REFERENCES pokedex (id_pokedex)
);

CREATE TABLE IF NOT EXISTS pokemon_tipos
(
    id_pokemon INT NOT NULL,
    id_tipo INT NOT NULL,
    PRIMARY KEY (id_pokemon, id_tipo),
    CONSTRAINT FK_pokemons_TO_pokemon_tipos
        FOREIGN KEY (id_pokemon) REFERENCES pokemons (id_pokemon),
    CONSTRAINT FK_tipos_TO_pokemon_tipos
        FOREIGN KEY (id_tipo) REFERENCES tipos (id_tipo)
);

CREATE TABLE IF NOT EXISTS times
(
    id_time INT NOT NULL AUTO_INCREMENT,
    id_perfil INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_time),
    CONSTRAINT FK_perfis_TO_times
        FOREIGN KEY (id_perfil) REFERENCES perfis (id_perfil)
);

CREATE TABLE IF NOT EXISTS time_pokemons
(
    id_time INT NOT NULL,
    id_pokemon INT NOT NULL,
    posicao INT NOT NULL,
    nivel TINYINT UNSIGNED NOT NULL DEFAULT 1,
    shiny TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_time, id_pokemon),
    CONSTRAINT FK_times_TO_time_pokemons
        FOREIGN KEY (id_time) REFERENCES times (id_time),
    CONSTRAINT FK_pokemons_TO_time_pokemons
        FOREIGN KEY (id_pokemon) REFERENCES pokemons (id_pokemon)
);

CREATE TABLE IF NOT EXISTS time_pokemon_movimentos
(
    id_time INT NOT NULL,
    id_pokemon INT NOT NULL,
    posicao TINYINT UNSIGNED NOT NULL,
    nome_movimento VARCHAR(60) NOT NULL,
    PRIMARY KEY (id_time, id_pokemon, posicao),
    UNIQUE KEY UQ_time_pokemon_movimento (id_time, id_pokemon, nome_movimento),
    CONSTRAINT FK_time_pokemon_movimentos_time_pokemon
        FOREIGN KEY (id_time, id_pokemon)
        REFERENCES time_pokemons (id_time, id_pokemon)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS batalhas
(
    id_batalha INT NOT NULL AUTO_INCREMENT,
    id_perfil INT NOT NULL,
    resultado VARCHAR(20) NOT NULL DEFAULT 'EM_ANDAMENTO',
    recompensa INT NOT NULL DEFAULT 0,
    iniciada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_em DATETIME NULL,
    area TINYINT UNSIGNED NOT NULL DEFAULT 0,
    treinador_numero SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    nome_oponente VARCHAR(100) NOT NULL DEFAULT '',
    estado_json MEDIUMTEXT NULL,
    PRIMARY KEY (id_batalha),
    KEY IX_batalhas_perfil_resultado (id_perfil, resultado),
    CONSTRAINT FK_perfis_TO_batalhas
        FOREIGN KEY (id_perfil) REFERENCES perfis (id_perfil)
);

CREATE TABLE IF NOT EXISTS batalha_pokemons
(
    id_batalha INT NOT NULL,
    id_pokemon INT NOT NULL,
    lado ENUM('JOGADOR', 'OPONENTE') NOT NULL,
    hp_inicial INT NOT NULL,
    hp_final INT NOT NULL,
    venceu BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id_batalha, id_pokemon),
    CONSTRAINT FK_batalhas_TO_batalha_pokemons
        FOREIGN KEY (id_batalha) REFERENCES batalhas (id_batalha),
    CONSTRAINT FK_pokemons_TO_batalha_pokemons
        FOREIGN KEY (id_pokemon) REFERENCES pokemons (id_pokemon)
);

CREATE TABLE IF NOT EXISTS progresso_jogo
(
    id_perfil INT NOT NULL,
    area_atual TINYINT UNSIGNED NOT NULL DEFAULT 0,
    vitorias_area SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    vitorias_elite TINYINT UNSIGNED NOT NULL DEFAULT 0,
    concluido BOOLEAN NOT NULL DEFAULT FALSE,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_perfil),
    CONSTRAINT FK_perfis_TO_progresso_jogo
        FOREIGN KEY (id_perfil) REFERENCES perfis (id_perfil)
);

CREATE OR REPLACE VIEW vw_perfis_usuario AS
SELECT p.id_perfil, p.id_usuario, p.nome_perfil, p.genero, p.pokedollar,
       u.nome AS nome_usuario, u.email, u.criado_em
  FROM perfis p
  JOIN usuarios u ON u.id_usuario = p.id_usuario;

CREATE OR REPLACE VIEW vw_catalogo_itens AS
SELECT i.id_item, i.nome, i.descricao, i.preco, i.valor_hp,
       i.cura_status, i.revive, i.evolucao, c.nome AS categoria
  FROM itens i
  JOIN categorias_itens c ON c.id_categoria = i.id_categoria;

CREATE OR REPLACE VIEW vw_pokemon_time AS
SELECT tm.id_perfil, tp.id_time, tp.id_pokemon, tp.posicao, tp.nivel, tp.shiny,
       pk.nome, pk.status, p.id_pokedex, p.name AS especie,
       p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE,
       (SELECT GROUP_CONCAT(tpm.nome_movimento ORDER BY tpm.posicao SEPARATOR ',')
          FROM time_pokemon_movimentos tpm
         WHERE tpm.id_time = tp.id_time AND tpm.id_pokemon = tp.id_pokemon
       ) AS move_pool,
       (SELECT GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ')
          FROM pokemon_tipos pt
          JOIN tipos t ON t.id_tipo = pt.id_tipo
         WHERE pt.id_pokemon = pk.id_pokemon
       ) AS tipos
  FROM times tm
  JOIN time_pokemons tp ON tp.id_time = tm.id_time
  JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
  JOIN pokedex p ON p.id_pokedex = pk.id_pokedex;

CREATE TABLE IF NOT EXISTS batalha_logs
(
    id_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_batalha INT NOT NULL,
    sequencia SMALLINT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'info',
    mensagem VARCHAR(500) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    UNIQUE KEY UQ_batalha_logs_sequencia (id_batalha, sequencia),
    CONSTRAINT FK_batalhas_TO_batalha_logs
        FOREIGN KEY (id_batalha) REFERENCES batalhas (id_batalha)
        ON DELETE CASCADE
);
