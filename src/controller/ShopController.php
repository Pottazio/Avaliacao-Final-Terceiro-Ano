<?php
declare(strict_types=1);

namespace src\controller;

use src\dao\LojaDAO;

class ShopController
{
    public function __construct(private LojaDAO $loja)
    {
    }

    public function carregarLoja(int $idPerfil): array
    {
        return [
            'saldo' => $this->loja->obterSaldo($idPerfil),
            'itens' => $this->loja->listarItens($idPerfil),
        ];
    }

    public function comprar(int $idPerfil, int $idItem): array
    {
        return $this->loja->comprarItem($idPerfil, $idItem);
    }
}