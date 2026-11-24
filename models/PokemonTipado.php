<?php
declare(strict_types=1);

namespace models;

abstract class PokemonTipado
{
    /** @var string[] Tipos herdados pela espécie (ex.: ["Fogo", "Voador"]) */
    protected array $tipos = [];

    /** @return string[] */
    public function getTipos(): array
    {
        return $this->tipos;
    }

    /**
     * Aceita um tipo ou vários; o limite de dois reflete os tipos da espécie.
     */
    public function setTipos(string|array $tipos): static
    {
        $argumentos = func_get_args();
        $lista = count($argumentos) > 1 ? $argumentos : (array) $tipos;

        $limpos = [];
        foreach ($lista as $tipo) {
            $tipo = trim((string) $tipo);
            if ($tipo !== '') {
                $limpos[] = $tipo;
            }
        }

        $limpos = array_values(array_unique($limpos));
        if ($limpos === []) {
            throw new DadosPokemonInvalidosException('O Pokémon precisa ter ao menos um tipo.');
        }
        if (count($limpos) > 2) {
            throw new DadosPokemonInvalidosException('Um Pokémon tem no máximo dois tipos.');
        }

        $this->tipos = $limpos;
        return $this;
    }
}
