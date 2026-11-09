
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS batalha_pokemons
(
  id_batalha INT                    NOT NULL,
  id_pokemon INT                    NOT NULL,
  lado       ENUM(JOGADOR,OPONENTE) NOT NULL,
  hp_inicial INT                    NOT NULL,
  hp_final   INT                    NOT NULL,
  venceu     BOOLEAN                NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id_batalha, id_pokemon),
  CONSTRAINT FK_batalhas_TO_batalha_pokemons
    FOREIGN KEY (id_batalha)
    REFERENCES batalhas (id_batalha),
  CONSTRAINT FK_pokemons_TO_batalha_pokemons
    FOREIGN KEY (id_pokemon)
    REFERENCES pokemons (id_pokemon)
);

CREATE TABLE IF NOT EXISTS batalhas
(
  id_batalha    INT                   NULL     AUTO_INCREMENT,
  id_perfil     INT                   NOT NULL,
  resultado     ENUM(VITORIA,DERROTA) NOT NULL,
  recompensa    INT                   NOT NULL DEFAULT 0,
  iniciada_em   DATETIME              NULL     DEFAULT CURRENT_TIMESTAMP,
  finalizada_em DATETIME              NULL    ,
  PRIMARY KEY (id_batalha),
  CONSTRAINT FK_perfis_TO_batalhas
    FOREIGN KEY (id_perfil)
    REFERENCES perfis (id_perfil)
);

CREATE TABLE IF NOT EXISTS categorias_itens
(
  id_categoria INT         NULL     AUTO_INCREMENT,
  nome         VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_categoria),
  CONSTRAINT UQ_categorias_itens_nome UNIQUE (nome)
);

CREATE TABLE IF NOT EXISTS compras
(
  id_compra      INT      NULL     AUTO_INCREMENT,
  id_perfil      INT      NOT NULL,
  id_item        INT      NOT NULL,
  quantidade     INT      NOT NULL,
  valor_unitario INT      NOT NULL,
  valor_total    INT      NOT NULL,
  comprado_em    DATETIME NULL     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_compra),
  CONSTRAINT FK_perfis_TO_compras
    FOREIGN KEY (id_perfil)
    REFERENCES perfis (id_perfil),
  CONSTRAINT FK_itens_TO_compras
    FOREIGN KEY (id_item)
    REFERENCES itens (id_item)
);

CREATE TABLE IF NOT EXISTS inventarios
(
  id_perfil  INT NOT NULL,
  id_item    INT NOT NULL,
  quantidade INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_perfil, id_item),
  CONSTRAINT FK_perfis_TO_inventarios
    FOREIGN KEY (id_perfil)
    REFERENCES perfis (id_perfil),
  CONSTRAINT FK_itens_TO_inventarios
    FOREIGN KEY (id_item)
    REFERENCES itens (id_item)
);

CREATE TABLE IF NOT EXISTS itens
(
  id_item      INT          NULL     AUTO_INCREMENT,
  id_categoria INT          NOT NULL,
  nome         VARCHAR(100) NOT NULL,
  descricao    VARCHAR(255) NULL    ,
  preco        INT          NOT NULL,
  valor_hp     INT          NULL     DEFAULT 0,
  cura_status  BOOLEAN      NULL     DEFAULT FALSE,
  revive       BOOLEAN      NULL     DEFAULT FALSE,
  evolucao     BOOLEAN      NULL     DEFAULT FALSE,
  PRIMARY KEY (id_item),
  CONSTRAINT FK_categorias_itens_TO_itens
    FOREIGN KEY (id_categoria)
    REFERENCES categorias_itens (id_categoria)
);

CREATE TABLE IF NOT EXISTS perfis
(
  id_perfil   INT         NULL     AUTO_INCREMENT,
  id_usuario  INT         NOT NULL,
  nome_perfil VARCHAR(50) NOT NULL DEFAULT (guest),
  pokedollar  INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (id_perfil),
  CONSTRAINT FK_usuarios_TO_perfis
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario)
);

CREATE TABLE IF NOT EXISTS pokedex
(
  id_pokedex INT          NOT NULL,
  name       VARCHAR(100) NOT NULL,
  HP         INT          NOT NULL,
  ATQ        INT          NOT NULL,
  ATQE       INT          NOT NULL,
  DEF        INT          NOT NULL,
  DEFE       INT          NOT NULL,
  SPE        INT          NOT NULL,
  PRIMARY KEY (id_pokedex)
);

CREATE TABLE IF NOT EXISTS pokemon_tipos
(
  id_pokemon INT NOT NULL,
  id_tipo    INT NOT NULL,
  PRIMARY KEY (id_pokemon, id_tipo),
  CONSTRAINT FK_pokemons_TO_pokemon_tipos
    FOREIGN KEY (id_pokemon)
    REFERENCES pokemons (id_pokemon),
  CONSTRAINT FK_tipos_TO_pokemon_tipos
    FOREIGN KEY (id_tipo)
    REFERENCES tipos (id_tipo)
);

CREATE TABLE IF NOT EXISTS pokemons
(
  id_pokemon INT          NOT NULL AUTO_INCREMENT,
  id_pokedex INT          NOT NULL,
  nome       VARCHAR(100) NOT NULL,
  status     VARCHAR(30)  NULL     DEFAULT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pokemon),
  CONSTRAINT FK_pokedex_TO_pokemons
    FOREIGN KEY (id_pokedex)
    REFERENCES pokedex (id_pokedex)
);

CREATE TABLE IF NOT EXISTS time_pokemons
(
  id_time    INT NOT NULL,
  id_pokemon INT NOT NULL,
  posicao    INT NOT NULL,
  PRIMARY KEY (id_time, id_pokemon),
  CONSTRAINT FK_times_TO_time_pokemons
    FOREIGN KEY (id_time)
    REFERENCES times (id_time),
  CONSTRAINT FK_pokemons_TO_time_pokemons
    FOREIGN KEY (id_pokemon)
    REFERENCES pokemons (id_pokemon)
);

CREATE TABLE IF NOT EXISTS times
(
  id_time   INT         NULL     AUTO_INCREMENT,
  id_perfil INT         NOT NULL,
  nome      VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_time),
  CONSTRAINT FK_perfis_TO_times
    FOREIGN KEY (id_perfil)
    REFERENCES perfis (id_perfil)
);

CREATE TABLE IF NOT EXISTS tipos
(
  id_tipo INT         NULL     AUTO_INCREMENT,
  nome    VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_tipo),
  CONSTRAINT UQ_tipos_nome UNIQUE (nome)
);

CREATE TABLE IF NOT EXISTS usuarios
(
  id_usuario INT          NULL     AUTO_INCREMENT,
  nome       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  senha      VARCHAR(255) NOT NULL,
  criado_em  DATETIME     NULL     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  CONSTRAINT UQ_usuarios_email UNIQUE (email)
);

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;