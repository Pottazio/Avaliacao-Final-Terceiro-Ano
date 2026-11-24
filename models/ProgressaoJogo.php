<?php
declare(strict_types=1);

namespace models;

use PDO;

class ProgressaoJogo
{
    public const AREAS = [
        0 => ['nome' => 'Área Inicial', 'nivel_maximo' => 5, 'vitorias_necessarias' => 5],
        1 => ['nome' => 'Área 1', 'nivel_maximo' => 20, 'vitorias_necessarias' => 5],
        2 => ['nome' => 'Área 2', 'nivel_maximo' => 40, 'vitorias_necessarias' => 10],
        3 => ['nome' => 'Área 3', 'nivel_maximo' => 60, 'vitorias_necessarias' => 15],
        4 => ['nome' => 'Área 4', 'nivel_maximo' => 80, 'vitorias_necessarias' => 20],
        5 => ['nome' => 'Área 5', 'nivel_maximo' => 100, 'vitorias_necessarias' => 25],
        6 => ['nome' => 'Elite dos 4', 'nivel_maximo' => 100, 'vitorias_necessarias' => 4],
        7 => ['nome' => 'Jogo concluído', 'nivel_maximo' => 100, 'vitorias_necessarias' => 0],
    ];

    public static function carregar(PDO $pdo, int $idPerfil): array
    {
        $inserir = $pdo->prepare(
            'INSERT IGNORE INTO progresso_jogo (id_perfil) VALUES (:id_perfil)'
        );
        $inserir->execute([':id_perfil' => $idPerfil]);

        $consulta = $pdo->prepare(
            'SELECT area_atual, vitorias_area, vitorias_elite, concluido
               FROM progresso_jogo
              WHERE id_perfil = :id_perfil'
        );
        $consulta->execute([':id_perfil' => $idPerfil]);
        $progresso = $consulta->fetch();

        if ($progresso === false) {
            throw new \RuntimeException('Não foi possível carregar o progresso do perfil.');
        }

        return self::descrever($progresso);
    }

    public static function descrever(array $progresso): array
    {
        $area = (int) $progresso['area_atual'];
        $configuracao = self::AREAS[$area] ?? self::AREAS[7];
        $vitorias = $area === 6
            ? (int) $progresso['vitorias_elite']
            : (int) $progresso['vitorias_area'];

        return [
            'area' => $area,
            'nome_area' => $configuracao['nome'],
            'nivel_maximo' => $configuracao['nivel_maximo'],
            'vitorias' => $vitorias,
            'vitorias_necessarias' => $configuracao['vitorias_necessarias'],
            'concluido' => (bool) $progresso['concluido'],
        ];
    }

    public static function nivelMaximo(int $area): int
    {
        return (self::AREAS[$area] ?? self::AREAS[7])['nivel_maximo'];
    }
}
