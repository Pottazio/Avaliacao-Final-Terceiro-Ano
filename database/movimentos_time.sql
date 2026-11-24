-- Aplicar uma vez em bancos existentes, após Bancojogo.sql.
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
