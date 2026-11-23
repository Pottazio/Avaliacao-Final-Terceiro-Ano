<?php
declare(strict_types=1);

namespace src\dao;

use PDO;
use RuntimeException;

class BatalhaDAO
{
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
            'SELECT pk.id_pokemon, pk.id_pokedex, pk.nome, p.name AS especie, tp.nivel,
                    p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE,
                    GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ", ") AS tipos
               FROM times tm
               JOIN time_pokemons tp ON tp.id_time = tm.id_time
               JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
               LEFT JOIN pokemon_tipos pt ON pt.id_pokemon = pk.id_pokemon
               LEFT JOIN tipos t ON t.id_tipo = pt.id_tipo
               WHERE tm.id_perfil = :id_perfil
                 AND p.id_pokedex BETWEEN 1 AND 151
              GROUP BY pk.id_pokemon, tp.nivel, tp.posicao
              ORDER BY tp.posicao'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        return $stmt->fetchAll();
    }

    public function buscarPokemonDoTime(int $idPerfil, int $idPokemon): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pk.id_pokemon, pk.id_pokedex, pk.nome, p.name AS especie, tp.nivel,
                    p.HP, p.ATQ, p.ATQE, p.DEF, p.DEFE, p.SPE
               FROM times tm
               JOIN time_pokemons tp ON tp.id_time = tm.id_time
               JOIN pokemons pk ON pk.id_pokemon = tp.id_pokemon
               JOIN pokedex p ON p.id_pokedex = pk.id_pokedex
              WHERE tm.id_perfil = :id_perfil
                AND pk.id_pokemon = :id_pokemon
                AND p.id_pokedex BETWEEN 1 AND 151
              LIMIT 1'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_pokemon' => $idPokemon,
        ]);
        $pokemon = $stmt->fetch();
        return $pokemon === false ? null : $pokemon;
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