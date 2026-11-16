<?php
declare(strict_types=1);

namespace src\dao;

use DomainException;
use PDO;
use Throwable;

class LojaDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listarItens(int $idPerfil): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id_item, i.nome, i.descricao, i.preco, c.nome AS categoria,
                    COALESCE(inv.quantidade, 0) AS quantidade
               FROM itens i
               JOIN categorias_itens c ON c.id_categoria = i.id_categoria
               LEFT JOIN inventarios inv
                 ON inv.id_item = i.id_item AND inv.id_perfil = :id_perfil
              ORDER BY c.nome, i.preco, i.nome'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);

        return $stmt->fetchAll();
    }

    public function obterSaldo(int $idPerfil): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT pokedollar FROM perfis WHERE id_perfil = :id_perfil'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        $saldo = $stmt->fetchColumn();

        if ($saldo === false) {
            throw new DomainException('Perfil não encontrado.');
        }

        return (int) $saldo;
    }

    public function comprarItem(int $idPerfil, int $idItem): array
    {
        if ($idPerfil <= 0 || $idItem <= 0) {
            throw new DomainException('Item ou perfil inválido.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmtPerfil = $this->pdo->prepare(
                'SELECT pokedollar FROM perfis WHERE id_perfil = :id_perfil FOR UPDATE'
            );
            $stmtPerfil->execute([':id_perfil' => $idPerfil]);
            $saldoAtual = $stmtPerfil->fetchColumn();

            if ($saldoAtual === false) {
                throw new DomainException('Perfil não encontrado.');
            }

            $stmtItem = $this->pdo->prepare(
                'SELECT id_item, nome, preco
                   FROM itens
                  WHERE id_item = :id_item
                  FOR UPDATE'
            );
            $stmtItem->execute([':id_item' => $idItem]);
            $item = $stmtItem->fetch();

            if ($item === false) {
                throw new DomainException('Esse item não está disponível na loja.');
            }

            $preco = (int) $item['preco'];
            $saldoAtual = (int) $saldoAtual;

            if ($preco < 0) {
                throw new DomainException('O preço deste item está inválido.');
            }

            if ($saldoAtual < $preco) {
                throw new DomainException('Saldo insuficiente para comprar este item.');
            }

            $stmtDebitar = $this->pdo->prepare(
                'UPDATE perfis
                    SET pokedollar = pokedollar - :preco
                  WHERE id_perfil = :id_perfil'
            );
            $stmtDebitar->execute([
                ':preco' => $preco,
                ':id_perfil' => $idPerfil,
            ]);

            $stmtInventario = $this->pdo->prepare(
                'INSERT INTO inventarios (id_perfil, id_item, quantidade)
                 VALUES (:id_perfil, :id_item, 1)
                 ON DUPLICATE KEY UPDATE quantidade = quantidade + 1'
            );
            $stmtInventario->execute([
                ':id_perfil' => $idPerfil,
                ':id_item' => $idItem,
            ]);

            $stmtCompra = $this->pdo->prepare(
                'INSERT INTO compras
                    (id_perfil, id_item, quantidade, valor_unitario, valor_total)
                 VALUES (:id_perfil, :id_item, 1, :valor_unitario, :valor_total)'
            );
            $stmtCompra->execute([
                ':id_perfil' => $idPerfil,
                ':id_item' => $idItem,
                ':valor_unitario' => $preco,
                ':valor_total' => $preco,
            ]);

            $this->pdo->commit();

            return [
                'nome' => (string) $item['nome'],
                'preco' => $preco,
                'saldo' => $saldoAtual - $preco,
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }
}