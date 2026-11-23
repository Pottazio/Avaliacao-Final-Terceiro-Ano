<?php
declare(strict_types=1);

namespace src\dao;

use PDO;

class ProgressaoDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function calcular(int $idPerfil): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM batalhas
             WHERE id_perfil = :id_perfil
               AND resultado = "VITORIA"'
        );

        $stmt->execute([':id_perfil' => $idPerfil]);
        $vitorias = (int) $stmt->fetchColumn();

        $pokedexMaxima = min(151, 30 + intdiv($vitorias, 5) * 30);

        return [
            'vitorias'       => $vitorias,
            'pokedex_maximo' => $pokedexMaxima,
        ];
    }
}