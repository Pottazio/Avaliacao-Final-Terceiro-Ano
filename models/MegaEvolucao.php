<?php
declare(strict_types=1);

namespace models;

use RuntimeException;

final class MegaEvolucao
{
    private const FORMAS = [
        'aerodactylite' => [
            'item' => 'Aerodactylite', 'pokemon' => 'Aerodactyl', 'id_pokedex' => 142,
            'forma' => 'aerodactyl-mega', 'nome_forma' => 'Mega Aerodactyl',
            'tipos' => ['rock', 'flying'],
            'stats' => ['ataque' => 135, 'defesa' => 85, 'ataque_especial' => 70, 'defesa_especial' => 95, 'velocidade' => 150],
        ],
        'alakazite' => [
            'item' => 'Alakazite', 'pokemon' => 'Alakazam', 'id_pokedex' => 65,
            'forma' => 'alakazam-mega', 'nome_forma' => 'Mega Alakazam',
            'tipos' => ['psychic'],
            'stats' => ['ataque' => 50, 'defesa' => 65, 'ataque_especial' => 175, 'defesa_especial' => 105, 'velocidade' => 150],
        ],
        'beedrillite' => [
            'item' => 'Beedrillite', 'pokemon' => 'Beedrill', 'id_pokedex' => 15,
            'forma' => 'beedrill-mega', 'nome_forma' => 'Mega Beedrill',
            'tipos' => ['bug', 'poison'],
            'stats' => ['ataque' => 150, 'defesa' => 40, 'ataque_especial' => 15, 'defesa_especial' => 80, 'velocidade' => 145],
        ],
        'blastoisinite' => [
            'item' => 'Blastoisinite', 'pokemon' => 'Blastoise', 'id_pokedex' => 9,
            'forma' => 'blastoise-mega', 'nome_forma' => 'Mega Blastoise',
            'tipos' => ['water'],
            'stats' => ['ataque' => 103, 'defesa' => 120, 'ataque_especial' => 135, 'defesa_especial' => 115, 'velocidade' => 78],
        ],
        'charizardite x' => [
            'item' => 'Charizardite X', 'pokemon' => 'Charizard', 'id_pokedex' => 6,
            'forma' => 'charizard-mega-x', 'nome_forma' => 'Mega Charizard X',
            'tipos' => ['fire', 'dragon'],
            'stats' => ['ataque' => 130, 'defesa' => 111, 'ataque_especial' => 130, 'defesa_especial' => 85, 'velocidade' => 100],
        ],
        'charizardite y' => [
            'item' => 'Charizardite Y', 'pokemon' => 'Charizard', 'id_pokedex' => 6,
            'forma' => 'charizard-mega-y', 'nome_forma' => 'Mega Charizard Y',
            'tipos' => ['fire', 'flying'],
            'stats' => ['ataque' => 104, 'defesa' => 78, 'ataque_especial' => 159, 'defesa_especial' => 115, 'velocidade' => 100],
        ],
        'gengarite' => [
            'item' => 'Gengarite', 'pokemon' => 'Gengar', 'id_pokedex' => 94,
            'forma' => 'gengar-mega', 'nome_forma' => 'Mega Gengar',
            'tipos' => ['ghost', 'poison'],
            'stats' => ['ataque' => 65, 'defesa' => 80, 'ataque_especial' => 170, 'defesa_especial' => 95, 'velocidade' => 130],
        ],
        'gyaradosite' => [
            'item' => 'Gyaradosite', 'pokemon' => 'Gyarados', 'id_pokedex' => 130,
            'forma' => 'gyarados-mega', 'nome_forma' => 'Mega Gyarados',
            'tipos' => ['water', 'dark'],
            'stats' => ['ataque' => 155, 'defesa' => 109, 'ataque_especial' => 70, 'defesa_especial' => 130, 'velocidade' => 81],
        ],
        'kangaskhanite' => [
            'item' => 'Kangaskhanite', 'pokemon' => 'Kangaskhan', 'id_pokedex' => 115,
            'forma' => 'kangaskhan-mega', 'nome_forma' => 'Mega Kangaskhan',
            'tipos' => ['normal'],
            'stats' => ['ataque' => 125, 'defesa' => 100, 'ataque_especial' => 60, 'defesa_especial' => 100, 'velocidade' => 100],
        ],
        'mewtwonite x' => [
            'item' => 'Mewtwonite X', 'pokemon' => 'Mewtwo', 'id_pokedex' => 150,
            'forma' => 'mewtwo-mega-x', 'nome_forma' => 'Mega Mewtwo X',
            'tipos' => ['psychic', 'fighting'],
            'stats' => ['ataque' => 190, 'defesa' => 100, 'ataque_especial' => 154, 'defesa_especial' => 100, 'velocidade' => 130],
        ],
        'mewtwonite y' => [
            'item' => 'Mewtwonite Y', 'pokemon' => 'Mewtwo', 'id_pokedex' => 150,
            'forma' => 'mewtwo-mega-y', 'nome_forma' => 'Mega Mewtwo Y',
            'tipos' => ['psychic'],
            'stats' => ['ataque' => 150, 'defesa' => 70, 'ataque_especial' => 194, 'defesa_especial' => 120, 'velocidade' => 140],
        ],
        'pidgeotite' => [
            'item' => 'Pidgeotite', 'pokemon' => 'Pidgeot', 'id_pokedex' => 18,
            'forma' => 'pidgeot-mega', 'nome_forma' => 'Mega Pidgeot',
            'tipos' => ['normal', 'flying'],
            'stats' => ['ataque' => 80, 'defesa' => 80, 'ataque_especial' => 135, 'defesa_especial' => 80, 'velocidade' => 121],
        ],
        'pinsirite' => [
            'item' => 'Pinsirite', 'pokemon' => 'Pinsir', 'id_pokedex' => 127,
            'forma' => 'pinsir-mega', 'nome_forma' => 'Mega Pinsir',
            'tipos' => ['bug', 'flying'],
            'stats' => ['ataque' => 155, 'defesa' => 120, 'ataque_especial' => 65, 'defesa_especial' => 90, 'velocidade' => 105],
        ],
        'slowbronite' => [
            'item' => 'Slowbronite', 'pokemon' => 'Slowbro', 'id_pokedex' => 80,
            'forma' => 'slowbro-mega', 'nome_forma' => 'Mega Slowbro',
            'tipos' => ['water', 'psychic'],
            'stats' => ['ataque' => 75, 'defesa' => 180, 'ataque_especial' => 130, 'defesa_especial' => 80, 'velocidade' => 30],
        ],
        'venusaurite' => [
            'item' => 'Venusaurite', 'pokemon' => 'Venusaur', 'id_pokedex' => 3,
            'forma' => 'venusaur-mega', 'nome_forma' => 'Mega Venusaur',
            'tipos' => ['grass', 'poison'],
            'stats' => ['ataque' => 100, 'defesa' => 123, 'ataque_especial' => 122, 'defesa_especial' => 120, 'velocidade' => 80],
        ],
    ];

    public static function itensLoja(): array
    {
        return array_map(
            static fn (array $mega): array => [
                'nome' => $mega['item'],
                'categoria' => 'Pedras Mega',
                'descricao' => "Mega Evolui {$mega['pokemon']}. A pedra não é consumida; disponível na Área 3.",
                'preco' => 500,
                'valor_hp' => 0,
                'cura_status' => false,
                'revive' => false,
                'evolucao' => true,
            ],
            array_values(self::FORMAS)
        );
    }

    public static function porItem(string $nomeItem): ?array
    {
        return self::FORMAS[strtolower(trim($nomeItem))] ?? null;
    }

    public static function porForma(string $forma): ?array
    {
        foreach (self::FORMAS as $mega) {
            if ($mega['forma'] === $forma) {
                return $mega;
            }
        }
        return null;
    }

    public static function aplicar(array &$pokemon, string $nomeItem): void
    {
        $mega = self::porItem($nomeItem);
        if ($mega === null) {
            throw new RuntimeException('Esta pedra não corresponde a uma Mega Evolução disponível.');
        }
        if ((int) ($pokemon['id_pokedex'] ?? 0) !== $mega['id_pokedex']) {
            throw new RuntimeException("{$mega['item']} só pode ser usada em {$mega['pokemon']}.");
        }
        if ((int) ($pokemon['hp'] ?? 0) <= 0) {
            throw new RuntimeException('Um Pokémon desmaiado não pode Mega Evoluir.');
        }
        if (!empty($pokemon['mega'])) {
            throw new RuntimeException('Este Pokémon já Mega Evoluiu nesta batalha.');
        }

        $nivel = max(1, (int) ($pokemon['nivel'] ?? 1));
        $adicionalNivel = intdiv($nivel, 2);
        foreach ($mega['stats'] as $atributo => $valor) {
            $pokemon[$atributo] = $valor + $adicionalNivel;
        }
        $pokemon['tipos'] = $mega['tipos'];
        $pokemon['mega'] = true;
        $pokemon['forma_mega'] = $mega['forma'];
        $pokemon['mega_nome'] = $mega['nome_forma'];
    }
}
