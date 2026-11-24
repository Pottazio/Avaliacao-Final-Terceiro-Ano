<?php
declare(strict_types=1);

namespace src\dao;

use PDO;
use Throwable;

class LojaCatalogoDAO
{
    private const ITENS = [
        [
            'nome' => 'Potion',
            'categoria' => 'Recuperacao de HP',
            'descricao' => 'Recupera 20 HP.',
            'preco' => 100,
            'valor_hp' => 20,
            'cura_status' => false,
            'revive' => false,
        ],
        [
            'nome' => 'Super Potion',
            'categoria' => 'Recuperacao de HP',
            'descricao' => 'Recupera 50 HP.',
            'preco' => 100,
            'valor_hp' => 50,
            'cura_status' => false,
            'revive' => false,
        ],
        [
            'nome' => 'Hyper Potion',
            'categoria' => 'Recuperacao de HP',
            'descricao' => 'Recupera 200 HP.',
            'preco' => 100,
            'valor_hp' => 200,
            'cura_status' => false,
            'revive' => false,
        ],
        [
            'nome' => 'Max Potion',
            'categoria' => 'Recuperacao de HP',
            'descricao' => 'Recupera 100% do HP.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => false,
            'revive' => false,
        ],
        [
            'nome' => 'Full Restore',
            'categoria' => 'Recuperacao de HP',
            'descricao' => 'Recupera 100% do HP e cura todas as condicoes de status.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Antidote',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura envenenamento.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Burn Heal',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura queimadura.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Ice Heal',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura congelamento.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Awakening',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura sono.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Paralyze Heal',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura paralisia.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Full Heal',
            'categoria' => 'Cura de status',
            'descricao' => 'Cura todos os status.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        [
            'nome' => 'Revive',
            'categoria' => 'Reviver',
            'descricao' => 'Revive um Pokemon desmaiado com metade da vida.',
            'preco' => 100,
            'valor_hp' => 0,
            'cura_status' => false,
            'revive' => true,
        ],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** Insere apenas os itens ausentes e preserva os registros existentes. */
    public function garantirCatalogo(): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmtCategoria = $this->pdo->prepare(
                'INSERT INTO categorias_itens (nome)
                 VALUES (:nome)
                 ON DUPLICATE KEY UPDATE id_categoria = LAST_INSERT_ID(id_categoria)'
            );
            $stmtItem = $this->pdo->prepare(
                'SELECT id_item
                   FROM itens
                  WHERE nome = :nome
                  ORDER BY id_item
                  LIMIT 1
                  FOR UPDATE'
            );
            $stmtInserir = $this->pdo->prepare(
                'INSERT INTO itens
                    (id_categoria, nome, descricao, preco, valor_hp, cura_status, revive, evolucao)
                 VALUES
                    (:id_categoria, :nome, :descricao, :preco, :valor_hp, :cura_status, :revive, 0)'
            );

            $inseridos = 0;
            foreach (self::ITENS as $item) {
                $stmtCategoria->execute([':nome' => $item['categoria']]);
                $idCategoria = (int) $this->pdo->lastInsertId();

                $stmtItem->execute([':nome' => $item['nome']]);
                if ($stmtItem->fetchColumn() !== false) {
                    continue;
                }

                $stmtInserir->execute([
                    ':id_categoria' => $idCategoria,
                    ':nome' => $item['nome'],
                    ':descricao' => $item['descricao'],
                    ':preco' => $item['preco'],
                    ':valor_hp' => $item['valor_hp'],
                    ':cura_status' => $item['cura_status'] ? 1 : 0,
                    ':revive' => $item['revive'] ? 1 : 0,
                ]);
                $inseridos++;
            }

            $this->pdo->commit();
            return $inseridos;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }
}
