<?php
declare(strict_types=1);

namespace models;

class Batalha
{
    private const EFETIVIDADE_TIPOS = [
        'normal' => [
            'resiste' => ['rock', 'steel'],
            'imune' => ['ghost'],
        ],
        'fire' => [
            'forte' => ['grass', 'ice', 'bug', 'steel'],
            'resiste' => ['fire', 'water', 'rock', 'dragon'],
        ],
        'water' => [
            'forte' => ['fire', 'ground', 'rock'],
            'resiste' => ['water', 'grass', 'dragon'],
        ],
        'electric' => [
            'forte' => ['water', 'flying'],
            'resiste' => ['electric', 'grass', 'dragon'],
            'imune' => ['ground'],
        ],
        'grass' => [
            'forte' => ['water', 'ground', 'rock'],
            'resiste' => ['fire', 'grass', 'poison', 'flying', 'bug', 'dragon', 'steel'],
        ],
        'ice' => [
            'forte' => ['grass', 'ground', 'flying', 'dragon'],
            'resiste' => ['fire', 'water', 'ice', 'steel'],
        ],
        'fighting' => [
            'forte' => ['normal', 'ice', 'rock', 'dark', 'steel'],
            'resiste' => ['poison', 'flying', 'psychic', 'bug', 'fairy'],
            'imune' => ['ghost'],
        ],
        'poison' => [
            'forte' => ['grass', 'fairy'],
            'resiste' => ['poison', 'ground', 'rock', 'ghost'],
            'imune' => ['steel'],
        ],
        'ground' => [
            'forte' => ['fire', 'electric', 'poison', 'rock', 'steel'],
            'resiste' => ['grass', 'bug'],
            'imune' => ['flying'],
        ],
        'flying' => [
            'forte' => ['grass', 'fighting', 'bug'],
            'resiste' => ['electric', 'rock', 'steel'],
        ],
        'psychic' => [
            'forte' => ['fighting', 'poison'],
            'resiste' => ['psychic', 'steel'],
            'imune' => ['dark'],
        ],
        'bug' => [
            'forte' => ['grass', 'psychic', 'dark'],
            'resiste' => ['fire', 'fighting', 'poison', 'flying', 'ghost', 'steel', 'fairy'],
        ],
        'rock' => [
            'forte' => ['fire', 'ice', 'flying', 'bug'],
            'resiste' => ['fighting', 'ground', 'steel'],
        ],
        'ghost' => [
            'forte' => ['psychic', 'ghost'],
            'resiste' => ['dark'],
            'imune' => ['normal'],
        ],
        'dragon' => [
            'forte' => ['dragon'],
            'resiste' => ['steel'],
            'imune' => ['fairy'],
        ],
        'dark' => [
            'forte' => ['psychic', 'ghost'],
            'resiste' => ['fighting', 'dark', 'fairy'],
        ],
        'steel' => [
            'forte' => ['ice', 'rock', 'fairy'],
            'resiste' => ['fire', 'water', 'electric', 'steel'],
        ],
        'fairy' => [
            'forte' => ['fighting', 'dragon', 'dark'],
            'resiste' => ['fire', 'poison', 'steel'],
        ],
    ];

    public static function combatente(
        array $pokemon,
        int $nivel,
        float $multiplicadorAtributos = 1.0
    ): array {
        $tipos = $pokemon['tipos'] ?? [];
        if (is_string($tipos)) {
            $tipos = explode(',', $tipos);
        }
        $multiplicadorAtributos = max(1.0, $multiplicadorAtributos);

        return [
            'id_pokemon' => (int) ($pokemon['id_pokemon'] ?? 0),
            'id_pokedex' => (int) ($pokemon['id_pokedex'] ?? 0),
            'nome' => (string) ($pokemon['nome'] ?? $pokemon['name'] ?? 'Pokémon'),
            'shiny' => (int) ($pokemon['shiny'] ?? 0) === 1,
            'mega' => false,
            'forma_mega' => null,
            'mega_nome' => null,
            'status' => strtoupper(trim((string) ($pokemon['status'] ?? 'NORMAL'))),
            'sleep_turns' => (int) ($pokemon['sleep_turns'] ?? 0),
            'tipos' => array_values(array_unique(array_filter(array_map(
                static fn ($tipo): string => strtolower(trim((string) $tipo)),
                (array) $tipos
            )))),
            'nivel' => $nivel,
            'hp_maximo' => max(
                1,
                (int) round(((int) $pokemon['HP'] + $nivel * 2) * $multiplicadorAtributos)
            ),
            'hp' => max(
                1,
                (int) round(((int) $pokemon['HP'] + $nivel * 2) * $multiplicadorAtributos)
            ),
            'ataque' => max(
                1,
                (int) round(((int) $pokemon['ATQ'] + intdiv($nivel, 2)) * $multiplicadorAtributos)
            ),
            'ataque_especial' => max(
                1,
                (int) round(
                    ((int) ($pokemon['ATQE'] ?? $pokemon['ATQ']) + intdiv($nivel, 2))
                    * $multiplicadorAtributos
                )
            ),
            'defesa' => max(
                1,
                (int) round(((int) $pokemon['DEF'] + intdiv($nivel, 2)) * $multiplicadorAtributos)
            ),
            'defesa_especial' => max(
                1,
                (int) round(
                    ((int) ($pokemon['DEFE'] ?? $pokemon['DEF']) + intdiv($nivel, 2))
                    * $multiplicadorAtributos
                )
            ),
            'velocidade' => max(
                1,
                (int) round(((int) $pokemon['SPE'] + intdiv($nivel, 2)) * $multiplicadorAtributos)
            ),
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

    public static function compararOrdemAtaques(
        array $ataqueA,
        array $combatenteA,
        array $ataqueB,
        array $combatenteB
    ): int {
        $prioridadeA = (int) ($ataqueA['prioridade'] ?? 0);
        $prioridadeB = (int) ($ataqueB['prioridade'] ?? 0);
        if ($prioridadeA !== $prioridadeB) {
            return $prioridadeA <=> $prioridadeB;
        }

        $velocidadeA = (int) ($combatenteA['velocidade'] ?? 0);
        $velocidadeB = (int) ($combatenteB['velocidade'] ?? 0);
        if (strtoupper((string) ($combatenteA['status'] ?? 'NORMAL')) === 'PARALYSIS') {
            $velocidadeA = max(1, (int) floor($velocidadeA / 2));
        }
        if (strtoupper((string) ($combatenteB['status'] ?? 'NORMAL')) === 'PARALYSIS') {
            $velocidadeB = max(1, (int) floor($velocidadeB / 2));
        }
        return $velocidadeA <=> $velocidadeB;
    }

    public static function calcularDano(
        array $atacante,
        array $defensor,
        int $poder = 40,
        string $classe = 'physical',
        ?string $tipoMovimento = null
    ): int
    {
        $calculador = $classe === 'special'
            ? new CalculadorAtaqueEspecial()
            : new CalculadorAtaqueFisico();
        [$ataque, $defesa] = $calculador->calcular($atacante, $defensor);
        if (
            $classe !== 'special'
            && strtoupper((string) ($atacante['status'] ?? 'NORMAL')) === 'BURN'
        ) {
            $ataque = max(1, (int) floor($ataque / 2));
        }
        $nivel = max(1, (int) $atacante['nivel']);
        $base = floor(((2 * $nivel / 5 + 2) * max(1, $poder) * $ataque / $defesa) / 50 + 2);
        $multiplicadorTipo = $tipoMovimento === null
            ? 1.0
            : self::multiplicadorTipo($tipoMovimento, $defensor['tipos'] ?? []);
        if ($multiplicadorTipo === 0.0) {
            return 0;
        }

        $bonusMesmoTipo = $tipoMovimento !== null
            && in_array($tipoMovimento, $atacante['tipos'] ?? [], true)
                ? 1.5
                : 1.0;

        return max(
            1,
            (int) floor($base * random_int(85, 100) / 100 * $multiplicadorTipo * $bonusMesmoTipo)
        );
    }

    public static function multiplicadorTipo(string $tipoAtaque, array $tiposDefensor): float
    {
        $tipoAtaque = strtolower(trim($tipoAtaque));
        $regras = self::EFETIVIDADE_TIPOS[$tipoAtaque] ?? [];
        $multiplicador = 1.0;

        foreach ($tiposDefensor as $tipoDefensor) {
            $tipoDefensor = strtolower(trim((string) $tipoDefensor));
            if (in_array($tipoDefensor, $regras['imune'] ?? [], true)) {
                return 0.0;
            }
            if (in_array($tipoDefensor, $regras['forte'] ?? [], true)) {
                $multiplicador *= 2.0;
            } elseif (in_array($tipoDefensor, $regras['resiste'] ?? [], true)) {
                $multiplicador *= 0.5;
            }
        }

        return $multiplicador;
    }
}