<?php
declare(strict_types=1);

namespace models;

use RuntimeException;

class ListaPokemonTreinadores
{
    private const POR_AREA = [
        0 => [
            ['id_pokedex' => 10, 'name' => 'Caterpie', 'HP' => 45, 'ATQ' => 30, 'DEF' => 35, 'SPE' => 45],
            ['id_pokedex' => 13, 'name' => 'Weedle', 'HP' => 40, 'ATQ' => 35, 'DEF' => 30, 'SPE' => 50],
            ['id_pokedex' => 16, 'name' => 'Pidgey', 'HP' => 40, 'ATQ' => 45, 'DEF' => 40, 'SPE' => 56],
            ['id_pokedex' => 19, 'name' => 'Rattata', 'HP' => 30, 'ATQ' => 56, 'DEF' => 35, 'SPE' => 72],
            ['id_pokedex' => 41, 'name' => 'Zubat', 'HP' => 40, 'ATQ' => 45, 'DEF' => 35, 'SPE' => 55],
        ],
        1 => [
            ['id_pokedex' => 25, 'name' => 'Pikachu', 'HP' => 35, 'ATQ' => 55, 'DEF' => 40, 'SPE' => 90],
            ['id_pokedex' => 27, 'name' => 'Sandshrew', 'HP' => 50, 'ATQ' => 75, 'DEF' => 85, 'SPE' => 40],
            ['id_pokedex' => 43, 'name' => 'Oddish', 'HP' => 45, 'ATQ' => 50, 'DEF' => 55, 'SPE' => 30],
            ['id_pokedex' => 60, 'name' => 'Poliwag', 'HP' => 40, 'ATQ' => 50, 'DEF' => 40, 'SPE' => 90],
            ['id_pokedex' => 74, 'name' => 'Geodude', 'HP' => 40, 'ATQ' => 80, 'DEF' => 100, 'SPE' => 20],
        ],
        2 => [
            ['id_pokedex' => 58, 'name' => 'Growlithe', 'HP' => 55, 'ATQ' => 70, 'DEF' => 45, 'SPE' => 60],
            ['id_pokedex' => 64, 'name' => 'Kadabra', 'HP' => 40, 'ATQ' => 35, 'DEF' => 30, 'SPE' => 105],
            ['id_pokedex' => 67, 'name' => 'Machoke', 'HP' => 80, 'ATQ' => 100, 'DEF' => 70, 'SPE' => 45],
            ['id_pokedex' => 75, 'name' => 'Graveler', 'HP' => 55, 'ATQ' => 95, 'DEF' => 115, 'SPE' => 35],
            ['id_pokedex' => 93, 'name' => 'Haunter', 'HP' => 45, 'ATQ' => 50, 'DEF' => 45, 'SPE' => 95],
        ],
        3 => [
            ['id_pokedex' => 78, 'name' => 'Rapidash', 'HP' => 65, 'ATQ' => 100, 'DEF' => 70, 'SPE' => 105],
            ['id_pokedex' => 55, 'name' => 'Golduck', 'HP' => 80, 'ATQ' => 82, 'DEF' => 78, 'SPE' => 85],
            ['id_pokedex' => 105, 'name' => 'Marowak', 'HP' => 60, 'ATQ' => 80, 'DEF' => 110, 'SPE' => 45],
            ['id_pokedex' => 101, 'name' => 'Electrode', 'HP' => 60, 'ATQ' => 50, 'DEF' => 70, 'SPE' => 150],
            ['id_pokedex' => 70, 'name' => 'Weepinbell', 'HP' => 65, 'ATQ' => 90, 'DEF' => 50, 'SPE' => 55],
        ],
        4 => [
            ['id_pokedex' => 130, 'name' => 'Gyarados', 'HP' => 95, 'ATQ' => 125, 'DEF' => 79, 'SPE' => 81],
            ['id_pokedex' => 59, 'name' => 'Arcanine', 'HP' => 90, 'ATQ' => 110, 'DEF' => 80, 'SPE' => 95],
            ['id_pokedex' => 65, 'name' => 'Alakazam', 'HP' => 55, 'ATQ' => 50, 'DEF' => 45, 'SPE' => 120],
            ['id_pokedex' => 68, 'name' => 'Machamp', 'HP' => 90, 'ATQ' => 130, 'DEF' => 80, 'SPE' => 55],
            ['id_pokedex' => 91, 'name' => 'Cloyster', 'HP' => 50, 'ATQ' => 95, 'DEF' => 180, 'SPE' => 70],
        ],
        5 => [
            ['id_pokedex' => 149, 'name' => 'Dragonite', 'HP' => 91, 'ATQ' => 134, 'DEF' => 95, 'SPE' => 80],
            ['id_pokedex' => 112, 'name' => 'Rhydon', 'HP' => 105, 'ATQ' => 130, 'DEF' => 120, 'SPE' => 40],
            ['id_pokedex' => 143, 'name' => 'Snorlax', 'HP' => 160, 'ATQ' => 110, 'DEF' => 65, 'SPE' => 30],
            ['id_pokedex' => 94, 'name' => 'Gengar', 'HP' => 60, 'ATQ' => 65, 'DEF' => 60, 'SPE' => 110],
            ['id_pokedex' => 131, 'name' => 'Lapras', 'HP' => 130, 'ATQ' => 85, 'DEF' => 80, 'SPE' => 60],
        ],
        6 => [
            ['id_pokedex' => 144, 'name' => 'Articuno', 'HP' => 90, 'ATQ' => 85, 'DEF' => 100, 'SPE' => 85],
            ['id_pokedex' => 145, 'name' => 'Zapdos', 'HP' => 90, 'ATQ' => 90, 'DEF' => 85, 'SPE' => 100],
            ['id_pokedex' => 146, 'name' => 'Moltres', 'HP' => 90, 'ATQ' => 100, 'DEF' => 90, 'SPE' => 90],
            ['id_pokedex' => 149, 'name' => 'Dragonite', 'HP' => 91, 'ATQ' => 134, 'DEF' => 95, 'SPE' => 80],
        ],
    ];

    public static function sortear(int $area): array
    {
        $lista = self::POR_AREA[$area] ?? null;
        if ($lista === null || $lista === []) {
            throw new RuntimeException('Não há Pokémon configurados para esta dificuldade.');
        }

        return $lista[random_int(0, count($lista) - 1)];
    }
}
