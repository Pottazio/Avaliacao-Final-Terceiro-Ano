<?php
declare(strict_types=1);

namespace src\dao;

use PDO;
use RuntimeException;

class BatalhaDAO
{
    private const FAIXA_POKEDEX_PRIMEIRA_GERACAO = [1, 151];

    private const POKEMON_LENDARIOS_E_MITICOS = [
        144, 145, 146, 150, 151,
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function garantirProgresso(int $idPerfil): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO progresso_jogo (id_perfil) VALUES (:id_perfil)'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
    }

    public function obterProgresso(int $idPerfil, bool $travar = false): array
    {
        $this->garantirProgresso($idPerfil);
        $sql = 'SELECT area_atual, vitorias_area, vitorias_elite, concluido
                  FROM progresso_jogo
                 WHERE id_perfil = :id_perfil';
        if ($travar) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_perfil' => $idPerfil]);
        $progresso = $stmt->fetch();
        if ($progresso === false) {
            throw new RuntimeException('Progresso do treinador não encontrado.');
        }

        return $progresso;
    }

    public function listarTime(int $idPerfil): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_pokemon, nome, status, id_pokedex, especie, nivel, shiny,
                    HP, ATQ, ATQE, DEF, DEFE, SPE, move_pool, tipos
               FROM vw_pokemon_time
              WHERE id_perfil = :id_perfil
              ORDER BY posicao'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        $pokemons = $stmt->fetchAll();
        foreach ($pokemons as &$pokemon) {
            $pokemon['move_pool'] = $pokemon['move_pool'] === null || $pokemon['move_pool'] === ''
                ? []
                : explode(',', $pokemon['move_pool']);
        }
        unset($pokemon);

        return $pokemons;
    }

    public function buscarPokemonDoTime(int $idPerfil, int $idPokemon): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_pokemon, nome, status, especie, nivel, shiny,
                    id_pokedex, HP, ATQ, ATQE, DEF, DEFE, SPE, tipos
               FROM vw_pokemon_time
              WHERE id_perfil = :id_perfil AND id_pokemon = :id_pokemon
              LIMIT 1'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_pokemon' => $idPokemon,
        ]);
        $pokemon = $stmt->fetch();
        return $pokemon === false ? null : $pokemon;
    }

    public function buscarTiposPokemon(int $idPokedex): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT t.nome
               FROM pokemons pk
               JOIN pokemon_tipos pt ON pt.id_pokemon = pk.id_pokemon
               JOIN tipos t ON t.id_tipo = pt.id_tipo
              WHERE pk.id_pokedex = :id_pokedex
              ORDER BY t.nome'
        );
        $stmt->execute([':id_pokedex' => $idPokedex]);
        return array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function buscarPokemonOponente(int $idPokedex, int $area): array
    {
        [$idMinimo, $idMaximo] = self::FAIXA_POKEDEX_PRIMEIRA_GERACAO;
        $exclusoes = implode(',', self::POKEMON_LENDARIOS_E_MITICOS);
        $stmt = $this->pdo->prepare(
            "SELECT id_pokedex, name, HP, ATQ, ATQE, DEF, DEFE, SPE
               FROM pokedex
              WHERE id_pokedex BETWEEN :id_minimo AND :id_maximo
                AND id_pokedex <> :id_pokedex
                AND id_pokedex NOT IN ({$exclusoes})
              ORDER BY RAND()
              LIMIT 1"
        );
        $stmt->execute([
            ':id_minimo' => $idMinimo,
            ':id_maximo' => $idMaximo,
            ':id_pokedex' => $idPokedex,
        ]);
        $pokemon = $stmt->fetch();
        if ($pokemon !== false) {
            $pokemon['tipos'] = $this->buscarTiposPokemon((int) $pokemon['id_pokedex']);
            return $pokemon;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id_pokedex, name, HP, ATQ, ATQE, DEF, DEFE, SPE
               FROM pokedex
              WHERE id_pokedex BETWEEN :id_minimo AND :id_maximo
                AND id_pokedex NOT IN ({$exclusoes})
              ORDER BY RAND()
              LIMIT 1"
        );
        $stmt->execute([
            ':id_minimo' => $idMinimo,
            ':id_maximo' => $idMaximo,
        ]);
        $pokemon = $stmt->fetch();
        if ($pokemon === false) {
            $stmt = $this->pdo->prepare(
                "SELECT id_pokedex, name, HP, ATQ, ATQE, DEF, DEFE, SPE
                   FROM pokedex
                  WHERE id_pokedex BETWEEN 1 AND 721
                    AND id_pokedex <> :id_pokedex
                    AND id_pokedex NOT IN ({$exclusoes})
                  ORDER BY RAND()
                  LIMIT 1"
            );
            $stmt->execute([
                ':id_pokedex' => $idPokedex,
            ]);
            $pokemon = $stmt->fetch();
        }
        if ($pokemon === false) {
            throw new RuntimeException('Não há Pokémon cadastrados para selecionar um adversário.');
        }
        $pokemon['tipos'] = $this->buscarTiposPokemon((int) $pokemon['id_pokedex']);
        return $pokemon;
    }

    public function pokemonElegivelOponente(int $idPokedex): bool
    {
        [$idMinimo, $idMaximo] = self::FAIXA_POKEDEX_PRIMEIRA_GERACAO;
        return $idPokedex >= $idMinimo
            && $idPokedex <= $idMaximo
            && !in_array($idPokedex, self::POKEMON_LENDARIOS_E_MITICOS, true);
    }

    public function listarIdsPokemonExcluidosOponente(): array
    {
        return self::POKEMON_LENDARIOS_E_MITICOS;
    }

    public function buscarBatalhaAtiva(int $idPerfil, bool $travar = false): ?array
    {
        $sql = "SELECT id_batalha, area, treinador_numero, nome_oponente, estado_json
                  FROM batalhas
                 WHERE id_perfil = :id_perfil AND resultado = 'EM_ANDAMENTO'
                 ORDER BY id_batalha DESC
                 LIMIT 1";
        if ($travar) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_perfil' => $idPerfil]);
        $batalha = $stmt->fetch();
        if ($batalha === false) {
            return null;
        }

        $estado = json_decode((string) $batalha['estado_json'], true);
        if (!\is_array($estado)) {
            throw new RuntimeException('O estado salvo da batalha está inválido.');
        }

        $batalha['id_batalha'] = (int) $batalha['id_batalha'];
        $batalha['estado'] = $estado;
        unset($batalha['estado_json']);
        return $batalha;
    }

    public function buscarUltimaBatalha(int $idPerfil): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_batalha, area, treinador_numero, nome_oponente, resultado
               FROM batalhas
              WHERE id_perfil = :id_perfil
              ORDER BY id_batalha DESC
              LIMIT 1'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        $batalha = $stmt->fetch();
        return $batalha === false ? null : $batalha;
    }

    public function listarLogs(int $idBatalha): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tipo, mensagem FROM batalha_logs
              WHERE id_batalha = :id_batalha
              ORDER BY sequencia'
        );
        $stmt->execute([':id_batalha' => $idBatalha]);
        return $stmt->fetchAll();
    }

    public function listarInventario(int $idPerfil): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id_item, i.nome, i.descricao, i.valor_hp, i.cura_status,
                    i.revive, i.evolucao, inv.quantidade
               FROM inventarios inv
               JOIN itens i ON i.id_item = inv.id_item
              WHERE inv.id_perfil = :id_perfil AND inv.quantidade > 0
              ORDER BY i.nome'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        return $stmt->fetchAll();
    }

    public function buscarItemInventario(int $idPerfil, int $idItem): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id_item, i.nome, i.descricao, i.valor_hp, i.cura_status,
                    i.revive, i.evolucao, inv.quantidade
               FROM inventarios inv
               JOIN itens i ON i.id_item = inv.id_item
              WHERE inv.id_perfil = :id_perfil
                AND inv.id_item = :id_item
              FOR UPDATE'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_item' => $idItem,
        ]);
        $item = $stmt->fetch();
        return $item === false ? null : $item;
    }

    public function consumirItem(int $idPerfil, int $idItem): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inventarios
                SET quantidade = quantidade - 1
              WHERE id_perfil = :id_perfil
                AND id_item = :id_item
                AND quantidade > 0'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_item' => $idItem,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Este item não está mais disponível na mochila.');
        }
    }

    public function iniciar(
        int $idPerfil,
        int $area,
        int $numeroTreinador,
        string $oponente,
        array $estado
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO batalhas
                (id_perfil, resultado, recompensa, area, treinador_numero, nome_oponente, estado_json)
             VALUES
                (:id_perfil, 'EM_ANDAMENTO', 0, :area, :treinador_numero, :nome_oponente, :estado_json)"
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':area' => $area,
            ':treinador_numero' => $numeroTreinador,
            ':nome_oponente' => $oponente,
            ':estado_json' => json_encode($estado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function salvarEstado(int $idBatalha, array $estado): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE batalhas SET estado_json = :estado_json WHERE id_batalha = :id_batalha'
        );
        $stmt->execute([
            ':estado_json' => json_encode($estado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ':id_batalha' => $idBatalha,
        ]);
    }

    public function registrarLog(int $idBatalha, string $tipo, string $mensagem): void
    {
        $proxima = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sequencia), 0) + 1
               FROM batalha_logs WHERE id_batalha = :id_batalha'
        );
        $proxima->execute([':id_batalha' => $idBatalha]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO batalha_logs (id_batalha, sequencia, tipo, mensagem)
             VALUES (:id_batalha, :sequencia, :tipo, :mensagem)'
        );
        $stmt->execute([
            ':id_batalha' => $idBatalha,
            ':sequencia' => (int) $proxima->fetchColumn(),
            ':tipo' => $tipo,
            ':mensagem' => mb_substr($mensagem, 0, 500),
        ]);
    }

    public function finalizar(int $idPerfil, int $idBatalha, string $resultado, int $recompensa): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE batalhas
                SET resultado = :resultado, recompensa = :recompensa,
                    finalizada_em = CURRENT_TIMESTAMP, estado_json = NULL
              WHERE id_batalha = :id_batalha AND id_perfil = :id_perfil'
        );
        $stmt->execute([
            ':resultado' => $resultado,
            ':recompensa' => $recompensa,
            ':id_batalha' => $idBatalha,
            ':id_perfil' => $idPerfil,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('A batalha não pôde ser finalizada.');
        }

        if ($recompensa > 0) {
            $saldo = $this->pdo->prepare(
                'UPDATE perfis SET pokedollar = pokedollar + :recompensa
                  WHERE id_perfil = :id_perfil'
            );
            $saldo->execute([
                ':recompensa' => $recompensa,
                ':id_perfil' => $idPerfil,
            ]);
        }
    }

    public function atualizarProgresso(int $idPerfil, array $progresso): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE progresso_jogo
                SET area_atual = :area_atual, vitorias_area = :vitorias_area,
                    vitorias_elite = :vitorias_elite, concluido = :concluido
              WHERE id_perfil = :id_perfil'
        );
        $stmt->execute([
            ':area_atual' => (int) $progresso['area_atual'],
            ':vitorias_area' => (int) $progresso['vitorias_area'],
            ':vitorias_elite' => (int) $progresso['vitorias_elite'],
            ':concluido' => (int) $progresso['concluido'],
            ':id_perfil' => $idPerfil,
        ]);
    }
}