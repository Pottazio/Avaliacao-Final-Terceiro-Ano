<?php
declare(strict_types=1);

namespace models;

use PDO;
use RuntimeException;
use config\Database;

/**
 * POKENOAS - Model Time
 *
 * AGREGACAO: Time agrega varios Pokemon (time_pokemons).
 * Os Pokemon existem por si sós (estão em `pokemons`); o Time só os reúne.
 * Se o Time deixar de existir, os Pokemon continuam existindo — é essa a
 * diferença entre agregação e composição.
 */
class Time
{
    /** Máximo de Pokémon por time. */
    public const MAX_POKEMON = 6;

    private ?int $idTime = null;
    private int $idPerfil = 0;
    private string $nome = '';

    /** @var array<int, Pokemon> Pokemon agregados */
    private array $pokemons = [];

    public function __construct(private PDO $conexao)
    {
    }

    // ---------- getters / setters ----------

    public function getIdTime(): ?int
    {
        return $this->idTime;
    }

    public function getIdPerfil(): int
    {
        return $this->idPerfil;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    /** @return array<int, Pokemon> */
    public function getPokemons(): array
    {
        return $this->pokemons;
    }

    public function setIdTime(int $id): self
    {
        $this->idTime = $id;
        return $this;
    }

    public function setIdPerfil(int $id): self
    {
        if ($id <= 0) {
            throw new TimeInvalidoException('Perfil inválido para o time.');
        }
        $this->idPerfil = $id;
        return $this;
    }

    public function setNome(string $nome): self
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new TimeInvalidoException('O time precisa de um nome.');
        }
        $this->nome = $nome;
        return $this;
    }

    /**
     * AGREGACAO: adiciona um Pokemon ao time.
     * Aceita um objeto Pokemon ou um id já existente.
     */
    public function adicionar(Pokemon|int $pokemon): self
    {
        if (count($this->pokemons) >= self::MAX_POKEMON) {
            throw new TimeInvalidoException('O time já tem ' . self::MAX_POKEMON . ' Pokémon.');
        }

        if ($pokemon instanceof Pokemon) {
            $id = $pokemon->getIdPokemon();
            if ($id === null) {
                throw new TimeInvalidoException('Salve o Pokémon antes de adicioná-lo ao time.');
            }
        } else {
            $id = $pokemon;
        }

        if (in_array($id, array_keys($this->pokemons), true)) {
            throw new TimeInvalidoException('Este Pokémon já está no time.');
        }

        if ($pokemon instanceof Pokemon) {
            $this->pokemons[$id] = $pokemon;
        } else {
            $novo = new Pokemon($this->conexao);
            $novo->setIdPokemon($id);
            $this->pokemons[$id] = $novo;
        }

        return $this;
    }

    /**
     * SOBRECARGA (simulada): adiciona um Pokemon ou vários de uma vez.
     * Com um argumento, comporta-se como adicionar(); com vários, percorre a lista.
     */
    public function adicionarVarios(int|array $pokemons, int ...$resto): self
    {
        $lista = (func_num_args() > 1) ? func_get_args() : (array) $pokemons;

        foreach ($lista as $item) {
            $this->adicionar(
                $item instanceof Pokemon ? $item : (int) $item
            );
        }

        return $this;
    }

    // ---------- persistência ----------

    /** Devolve o time do perfil, criando um vazio se ainda não existir. */
    public function doPerfil(int $idPerfil): self
    {
        $stmt = $this->conexao->prepare(
            'SELECT id_time, nome FROM times WHERE id_perfil = :id_perfil ORDER BY id_time LIMIT 1'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        $linha = $stmt->fetch();

        if ($linha === false) {
            return $this->criarPara($idPerfil);
        }

        $this->idTime   = (int) $linha['id_time'];
        $this->idPerfil = $idPerfil;
        $this->nome     = (string) $linha['nome'];

        return $this;
    }

    private function criarPara(int $idPerfil): self
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO times (id_perfil, nome) VALUES (:id_perfil, :nome)'
        );
        $stmt->execute([':id_perfil' => $idPerfil, ':nome' => 'Meu Time']);

        $this->idTime   = (int) $this->conexao->lastInsertId();
        $this->idPerfil = $idPerfil;
        $this->nome     = 'Meu Time';

        return $this;
    }

    /**
     * Insere o Pokemon no time, na próxima posição livre.
     * Devolve a posição usada.
     */
    public function inserirPokemon(int $idPokemon, int $nivel = 1): int
    {
        if ($this->idTime === null) {
            throw new TimeInvalidoException('Time não carregado.');
        }
        if ($nivel < 1 || $nivel > 100) {
            throw new TimeInvalidoException('O nível do Pokémon deve estar entre 1 e 100.');
        }
        $pokedex = $this->conexao->prepare(
            'SELECT p.id_pokedex
               FROM pokemons pk
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
              WHERE pk.id_pokemon = :id_pokemon
                AND p.id_pokedex BETWEEN 1 AND 151'
        );
        $pokedex->execute([':id_pokemon' => $idPokemon]);
        if ($pokedex->fetchColumn() === false) {
            throw new TimeInvalidoException('Só é possível adicionar Pokémon da primeira geração (#001–#151).');
        }

        $posicao = $this->proximaPosicao();

        if ($posicao > self::MAX_POKEMON) {
            throw new TimeInvalidoException('O time já tem ' . self::MAX_POKEMON . ' Pokémon.');
        }

        $jaTem = $this->conexao->prepare(
            'SELECT 1 FROM time_pokemons WHERE id_time = :id_time AND id_pokemon = :id_pokemon'
        );
        $jaTem->execute([
            ':id_time'    => $this->idTime,
            ':id_pokemon' => $idPokemon,
        ]);
        if ($jaTem->fetchColumn() !== false) {
            throw new TimeInvalidoException('Este Pokémon já está no time.');
        }

        $stmt = $this->conexao->prepare(
            'INSERT INTO time_pokemons (id_time, id_pokemon, posicao, nivel)
             VALUES (:id_time, :id_pokemon, :posicao, :nivel)'
        );
        $stmt->execute([
            ':id_time'    => $this->idTime,
            ':id_pokemon' => $idPokemon,
            ':posicao'    => $posicao,
            ':nivel'      => $nivel,
        ]);

        return $posicao;
    }

    public function atualizarNivel(int $idPokemon, int $nivel): bool
    {
        if ($this->idTime === null) {
            throw new TimeInvalidoException('Time não carregado.');
        }
        if ($nivel < 1 || $nivel > 100) {
            throw new TimeInvalidoException('O nível do Pokémon deve estar entre 1 e 100.');
        }

        $stmt = $this->conexao->prepare(
            'UPDATE time_pokemons tp
              JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
              JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
                SET tp.nivel = :nivel
              WHERE tp.id_time = :id_time AND tp.id_pokemon = :id_pokemon
                AND p.id_pokedex BETWEEN 1 AND 151'
        );
        $stmt->execute([
            ':nivel' => $nivel,
            ':id_time' => $this->idTime,
            ':id_pokemon' => $idPokemon,
        ]);
        if ($stmt->rowCount() > 0) {
            return true;
        }

        $consulta = $this->conexao->prepare(
            'SELECT 1
               FROM time_pokemons tp
               JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
              WHERE tp.id_time = :id_time AND tp.id_pokemon = :id_pokemon
                AND p.id_pokedex BETWEEN 1 AND 151'
        );
        $consulta->execute([
            ':id_time' => $this->idTime,
            ':id_pokemon' => $idPokemon,
        ]);
        return $consulta->fetchColumn() !== false;
    }

    public function removerPokemon(int $idPokemon): bool
    {
        if ($this->idTime === null) {
            throw new TimeInvalidoException('Time não carregado.');
        }

        $stmt = $this->conexao->prepare(
            'DELETE FROM time_pokemons WHERE id_time = :id_time AND id_pokemon = :id_pokemon'
        );
        $stmt->execute([
            ':id_time'    => $this->idTime,
            ':id_pokemon' => $idPokemon,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function proximaPosicao(): int
    {
        $stmt = $this->conexao->prepare(
            'SELECT COALESCE(MAX(posicao), 0) + 1 FROM time_pokemons WHERE id_time = :id_time'
        );
        $stmt->execute([':id_time' => $this->idTime]);

        return (int) $stmt->fetchColumn();
    }

    /** Lista os Pokemon do time, já com os tipos concatenados. */
    public function listarPokemons(): array
    {
        if ($this->idTime === null) {
            return [];
        }

        $sql = 'SELECT pk.id_pokemon, pk.id_pokedex,
                       pk.nome,
                       pk.status,
                       p.name AS especie,
                       p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE,
                       tp.posicao, tp.nivel,
                       GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ", ") AS tipos
                  FROM time_pokemons tp
                  JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
                  JOIN pokedex  p  ON p.id_pokedex  = pk.id_pokedex
                  LEFT JOIN pokemon_tipos pt ON pt.id_pokemon = pk.id_pokemon
                  LEFT JOIN tipos t          ON t.id_tipo    = pt.id_tipo
                  WHERE tp.id_time = :id_time
                    AND p.id_pokedex BETWEEN 1 AND 151
                 GROUP BY pk.id_pokemon, tp.posicao
                 ORDER BY tp.posicao';

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id_time' => $this->idTime]);

        return $stmt->fetchAll();
    }
}

/** Exceção customizada do modulo Time. */
class TimeInvalidoException extends RuntimeException
{
}
