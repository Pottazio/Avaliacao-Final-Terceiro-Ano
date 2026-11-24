<?php
declare(strict_types=1);

namespace src\dao;

use PDO;
use RuntimeException;

class BatalhaDAO
{
    private const FAIXAS_POKEDEX_AREA = [
        0 => [1, 151],
        1 => [152, 251],
        2 => [252, 386],
        3 => [387, 493],
        4 => [494, 649],
        5 => [650, 721],
        6 => [1, 721],
    ];

    private const POKEMON_LENDARIOS_E_MITICOS = [
        144, 145, 146, 150, 151,
        243, 244, 245, 249, 250, 251,
        377, 378, 379, 380, 381, 382, 383, 384, 385, 386,
        480, 481, 482, 483, 484, 485, 486, 487, 488, 489, 490, 491, 492, 493,
        494, 638, 639, 640, 641, 642, 643, 644, 645, 646, 647, 648, 649,
        716, 717, 718, 719, 720, 721,
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
            'SELECT pk.id_pokemon, pk.nome, p.id_pokedex, p.name AS especie, tp.nivel,
                    p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE,
                    GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ", ") AS tipos
               FROM times tm
               JOIN time_pokemons tp ON tp.id_time = tm.id_time
               JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
               LEFT JOIN pokemon_tipos pt ON pt.id_pokemon = pk.id_pokemon
               LEFT JOIN tipos t ON t.id_tipo = pt.id_tipo
              WHERE tm.id_perfil = :id_perfil
              GROUP BY pk.id_pokemon, tp.nivel, tp.posicao, p.id_pokedex,
                       p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE
              ORDER BY tp.posicao'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        return $stmt->fetchAll();
    }

    public function buscarPokemonDoTime(int $idPerfil, int $idPokemon): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pk.id_pokemon, pk.nome, p.name AS especie, tp.nivel,
                    p.id_pokedex, p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE
               FROM times tm
               JOIN time_pokemons tp ON tp.id_time = tm.id_time
               JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
              WHERE tm.id_perfil = :id_perfil AND pk.id_pokemon = :id_pokemon
              LIMIT 1'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_pokemon' => $idPokemon,
        ]);
        $pokemon = $stmt->fetch();
        if ($pokemon !== false) {
            $pokemon['tipos'] = $this->buscarTiposPokemon((int) $pokemon['id_pokedex']);
        }
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
        [$idMinimo, $idMaximo] = self::FAIXAS_POKEDEX_AREA[$area] ?? self::FAIXAS_POKEDEX_AREA[6];
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