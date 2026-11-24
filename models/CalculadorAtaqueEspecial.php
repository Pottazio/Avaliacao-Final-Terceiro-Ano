<?php
declare(strict_types=1);

namespace models;

final class CalculadorAtaqueEspecial extends CalculadorAtributosAtaque
{
    public function calcular(array $atacante, array $defensor): array
    {
        return [
            $this->aplicarEstagio(
                (int) ($atacante['ataque_especial'] ?? $atacante['ataque'] ?? 1),
                (int) ($atacante['estagio_ataque'] ?? 0)
            ),
            $this->aplicarEstagio(
                (int) ($defensor['defesa_especial'] ?? $defensor['defesa'] ?? 1),
                (int) ($defensor['estagio_defesa'] ?? 0)
            ),
        ];
    }
}
