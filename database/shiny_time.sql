-- Aplicar uma vez em bancos existentes, após Bancojogo.sql.
ALTER TABLE time_pokemons
    ADD COLUMN shiny TINYINT(1) NOT NULL DEFAULT 0;
