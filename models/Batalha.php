<?php
declare(strict_types=1);

namespace models;

class Batalha
{
    public static function combatente(array $pokemon, int $nivel): array
    {
        return [
            'id_pokemon' => (int) ($pokemon['id_pokemon'] ?? 0),
            'id_pokedex' => (int) ($pokemon['id_pokedex'] ?? 0),
            'nome' => (string) ($pokemon['nome'] ?? $pokemon['name'] ?? 'Pokémon'),
            'nivel' => $nivel,
            'hp_maximo' => max(1, (int) $pokemon['HP'] + $nivel * 2),
            'hp' => max(1, (int) $pokemon['HP'] + $nivel * 2),
            'ataque' => max(1, (int) $pokemon['ATQ'] + intdiv($nivel, 2)),
            'ataque_especial' => max(1, (int) ($pokemon['ATQE'] ?? $pokemon['ATQ']) + intdiv($nivel, 2)),
            'defesa' => max(1, (int) $pokemon['DEF'] + intdiv($nivel, 2)),
            'defesa_especial' => max(1, (int) ($pokemon['DEFE'] ?? $pokemon['DEF']) + intdiv($nivel, 2)),
            'velocidade' => max(1, (int) $pokemon['SPE'] + intdiv($nivel, 2)),
            'estagio_ataque' => 0,
            'estagio_defesa' => 0,
        ];
    }

    public static function multiplicadorEstagio(int $estagio): float
    {
        $estagio = max(-6, min(6, $estagio));
        return $estagio >= 0
            ? (2 + $estagio) / 2
            : 2 / (2 + abs($estagio));
    }

    public static function calcularDano(
        array $atacante,
        array $defensor,
        int $poder = 40,
        string $classe = 'physical'
    ): int
    {
        $atributoAtaque = $classe === 'special' ? 'ataque_especial' : 'ataque';
        $atributoDefesa = $classe === 'special' ? 'defesa_especial' : 'defesa';
        $ataque = max(
            1,
            (int) floor($atacante[$atributoAtaque] * self::multiplicadorEstagio($atacante['estagio_ataque']))
        );
        $defesa = max(
            1,
            (int) floor($defensor[$atributoDefesa] * self::multiplicadorEstagio($defensor['estagio_defesa']))
        );
        $nivel = max(1, (int) $atacante['nivel']);
        $base = floor(((2 * $nivel / 5 + 2) * max(1, $poder) * $ataque / $defesa) / 50 + 2);

        return max(1, (int) floor($base * random_int(85, 100) / 100));
    }
}