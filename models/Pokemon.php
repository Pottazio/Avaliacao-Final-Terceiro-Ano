<?php
declare(strict_types=1);

namespace models;

use PDO;
use RuntimeException;

/**
 * POKENOAS - Model Pokemon
 *
 * HERANCA: Pokemon recebe os tipos definidos em PokemonTipado.
 * ASSOCIACAO: Pokemon <-> Tipo (N:N, resolvida pela tabela pokemon_tipos).
 * COMPOSICAO: Pokemon mantém seus atributos base como um objeto de valor.
 */
class Pokemon extends PokemonTipado
{
    public const MAX_PRIMEIRA_GERACAO = 151;

    private ?int $idPokemon = null;
    private int $idPokedex = 0;
    private string $nome = '';
    private ?string $status = null;

    private ?AtributosPokemon $atributos = null;

    public function __construct(private PDO $conexao)
    {
    }

    // ---------- getters / setters (encapsulamento) ----------

    public function getIdPokemon(): ?int
    {
        return $this->idPokemon;
    }

    public function getIdPokedex(): int
    {
        return $this->idPokedex;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getAtributos(): ?AtributosPokemon
    {
        return $this->atributos;
    }

    public function setIdPokemon(int $id): self
    {
        $this->idPokemon = $id;
        return $this;
    }

    public function setIdPokedex(int $id): self
    {
        if ($id < 1 || $id > self::MAX_PRIMEIRA_GERACAO) {
            throw new DadosPokemonInvalidosException('Escolha um Pokémon da primeira geração (#001–#151).');
        }
        $this->idPokedex = $id;
        return $this;
    }

    public function setNome(string $nome): self
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new DadosPokemonInvalidosException('O nome do Pokémon é obrigatório.');
        }
        if (mb_strlen($nome) > 100) {
            throw new DadosPokemonInvalidosException('Nome de Pokémon muito longo.');
        }
        $this->nome = $nome;
        return $this;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    // ---------- persistência ----------

    /**
     * Grava o Pokémon da Pokedex (se ainda não existir) e devolve o id.
     * Os atributos vêm da PokeAPI e casam com as colunas de `pokedex`.
     */
    public function salvarNaPokedex(int $idPokedex, string $especie, array $atributos): void
    {
        if ($idPokedex < 1 || $idPokedex > self::MAX_PRIMEIRA_GERACAO) {
            throw new DadosPokemonInvalidosException('Só é permitido cadastrar Pokémon da primeira geração (#001–#151).');
        }

        $this->atributos = new AtributosPokemon($atributos);
        $valores = $this->atributos->paraPersistencia();

        $sql = 'INSERT INTO pokedex (id_pokedex, name, HP, ATQ, ATQE, DEF, DEFE, SPE)
                VALUES (:id_pokedex, :name, :HP, :ATQ, :ATQE, :DEF, :DEFE, :SPE)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    HP   = VALUES(HP),
                    ATQ  = VALUES(ATQ),
                    ATQE = VALUES(ATQE),
                    DEF  = VALUES(DEF),
                    DEFE = VALUES(DEFE),
                    SPE  = VALUES(SPE)';

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([
            ':id_pokedex' => $idPokedex,
            ':name'       => $especie,
            ':HP'         => $valores['HP'],
            ':ATQ'        => $valores['ATQ'],
            ':ATQE'       => $valores['ATQE'],
            ':DEF'        => $valores['DEF'],
            ':DEFE'       => $valores['DEFE'],
            ':SPE'        => $valores['SPE'],
        ]);
    }

    /**
     * Insere o Pokemon e associa os tipos.
     * Tudo dentro de uma transação (DTL): se um tipo falhar, nada fica pela metade.
     */
    public function salvar(): int
    {
        try {
            $this->conexao->beginTransaction();

            $sql = 'INSERT INTO pokemons (id_pokedex, nome, status)
                    VALUES (:id_pokedex, :nome, :status)';
            $stmt = $this->conexao->prepare($sql);
            $stmt->execute([
                ':id_pokedex' => $this->idPokedex,
                ':nome'       => $this->nome,
                ':status'     => $this->status,
            ]);

            $this->idPokemon = (int) $this->conexao->lastInsertId();

            // Associação Pokemon <-> Tipo
            foreach ($this->tipos as $nomeTipo) {
                $idTipo = $this->buscarOuCriarTipo($nomeTipo);

                $stmtTipo = $this->conexao->prepare(
                    'INSERT INTO pokemon_tipos (id_pokemon, id_tipo)
                     VALUES (:id_pokemon, :id_tipo)'
                );
                $stmtTipo->execute([
                    ':id_pokemon' => $this->idPokemon,
                    ':id_tipo'    => $idTipo,
                ]);
            }

            $this->conexao->commit();

            return $this->idPokemon;
        } catch (RuntimeException $e) {
            if ($this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }
            throw $e;
        }
    }

    /** Devolve o id do tipo, criando a linha em `tipos` se for novo. */
    private function buscarOuCriarTipo(string $nome): int
    {
        $stmt = $this->conexao->prepare('SELECT id_tipo FROM tipos WHERE nome = :nome LIMIT 1');
        $stmt->execute([':nome' => $nome]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->conexao->prepare('INSERT INTO tipos (nome) VALUES (:nome)');
        $stmt->execute([':nome' => $nome]);

        return (int) $this->conexao->lastInsertId();
    }

    /** Busca um Pokemon pelo nome, com os tipos associados. */
    public function buscarPorNome(string $nome): ?array
    {
        $sql = 'SELECT pk.id_pokemon, pk.nome, pk.status, pk.id_pokedex,
                       p.name AS especie, p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE,
                       GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ", ") AS tipos
                  FROM pokemons pk
                  JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
                  LEFT JOIN pokemon_tipos pt ON pt.id_pokemon = pk.id_pokemon
                  LEFT JOIN tipos t          ON t.id_tipo = pt.id_tipo
                 WHERE pk.nome = :nome
                 GROUP BY pk.id_pokemon, pk.nome, pk.status, pk.id_pokedex,
                          p.name, p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE
                 LIMIT 1';

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':nome' => $nome]);

        $linha = $stmt->fetch();
        return $linha === false ? null : $linha;
    }
}
