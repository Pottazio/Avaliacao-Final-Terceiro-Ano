-- Execute uma única vez, depois de criar o esquema base do Bancojogo.

ALTER TABLE time_pokemons
    ADD COLUMN nivel TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER posicao;

ALTER TABLE batalhas
    MODIFY COLUMN resultado VARCHAR(20) NOT NULL DEFAULT 'EM_ANDAMENTO',
    ADD COLUMN area TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN treinador_numero SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN nome_oponente VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN estado_json MEDIUMTEXT NULL,
    ADD KEY IX_batalhas_perfil_resultado (id_perfil, resultado);

CREATE TABLE progresso_jogo
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

CREATE TABLE batalha_logs
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
