-- Aplicar uma vez em bancos existentes após Bancojogo.sql.
ALTER TABLE perfis
    ADD COLUMN genero VARCHAR(12) NOT NULL DEFAULT 'masculino';
