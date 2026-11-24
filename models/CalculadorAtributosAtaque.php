<?php
declare(strict_types=1);

namespace models;

abstract class CalculadorAtributosAtaque
{
    abstract public function calcular(array $atacante, array $defensor): array;

    protected function aplicarEstagio(int $valor, int $estagio): int
    {
        return max(1, (int) floor($valor * Batalha::multiplicadorEstagio($estagio)));
    }
}
