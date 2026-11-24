<?php
declare(strict_types=1);

namespace models;

final class AtributosPokemon
{
    private array $valores;

    public function __construct(array $valores)
    {
        $this->valores = [];
        foreach (['HP', 'ATQ', 'ATQE', 'DEF', 'DEFE', 'SPE'] as $atributo) {
            $valor = $valores[$atributo] ?? 0;
            if (!is_numeric($valor) || (int) $valor < 0) {
                throw new DadosPokemonInvalidosException("Atributo {$atributo} inválido.");
            }
            $this->valores[$atributo] = (int) $valor;
        }
    }

    public function paraPersistencia(): array
    {
        return $this->valores;
    }
}
