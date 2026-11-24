-- Em bancos existentes, execute as migrações (incluindo perfil_genero.sql) antes deste arquivo.
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
