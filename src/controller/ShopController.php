<?php
declare(strict_types=1);

namespace src\controller;

use PDO;
use RuntimeException;
use src\core\Auth;

class ShopController
{
    private const ITENS = [
        'potion' => [
            'nome' => 'Potion',
            'preco' => 300,
            'categoria' => 'healing',
            'valor_hp' => 20,
            'cura_status' => false,
            'revive' => false,
        ],
        'super-potion' => [
            'nome' => 'Super Potion',
            'preco' => 700,
            'categoria' => 'healing',
            'valor_hp' => 50,
            'cura_status' => false,
            'revive' => false,
        ],
        'hyper-potion' => [
            'nome' => 'Hyper Potion',
            'preco' => 1200,
            'categoria' => 'healing',
            'valor_hp' => 200,
            'cura_status' => false,
            'revive' => false,
        ],
        'max-potion' => [
            'nome' => 'Max Potion',
            'preco' => 2500,
            'categoria' => 'healing',
            'valor_hp' => 0,
            'cura_status' => false,
            'revive' => false,
        ],
        'full-restore' => [
            'nome' => 'Full Restore',
            'preco' => 3000,
            'categoria' => 'healing',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'antidote' => [
            'nome' => 'Antidote',
            'preco' => 100,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'burn-heal' => [
            'nome' => 'Burn Heal',
            'preco' => 250,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'ice-heal' => [
            'nome' => 'Ice Heal',
            'preco' => 250,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'awakening' => [
            'nome' => 'Awakening',
            'preco' => 250,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'paralyze-heal' => [
            'nome' => 'Paralyze Heal',
            'preco' => 200,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'full-heal' => [
            'nome' => 'Full Heal',
            'preco' => 600,
            'categoria' => 'status-cures',
            'valor_hp' => 0,
            'cura_status' => true,
            'revive' => false,
        ],
        'revive' => [
            'nome' => 'Revive',
            'preco' => 1500,
            'categoria' => 'revival',
            'valor_hp' => 0,
            'cura_status' => false,
            'revive' => true,
        ],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function listar(): void
    {
        if (!$this->exigirLogin()) {
            return;
        }

        $idPerfil = Auth::idPerfil();
        $stmt = $this->pdo->prepare(
            'SELECT i.id_item, i.nome, i.descricao, i.preco,
                    COALESCE(inv.quantidade, 0) AS quantidade
               FROM itens i
               LEFT JOIN inventarios inv
                 ON inv.id_item = i.id_item
                AND inv.id_perfil = :id_perfil
              ORDER BY i.nome'
        );
        $stmt->execute([':id_perfil' => $idPerfil]);
        $itens = $stmt->fetchAll();

        $saldo = $this->buscarSaldo($idPerfil);
        $token = $_SESSION['csrf_loja'] ?? bin2hex(random_bytes(32));
        $_SESSION['csrf_loja'] = $token;

        $this->responder(200, [
            'sucesso' => true,
            'itens' => $itens,
            'saldo' => $saldo,
            'csrfToken' => $token,
        ]);
    }

    public function sincronizar(): void
    {
        if (!$this->exigirLogin() || !$this->validarMetodo('POST') || !$this->validarCsrf()) {
            return;
        }

        $dados = $this->lerJson();
        if ($dados === null) {
            return;
        }
        $itensRecebidos = $dados['itens'] ?? null;
        if (!is_array($itensRecebidos) || $itensRecebidos === []) {
            $this->responder(400, ['sucesso' => false, 'mensagem' => 'A lista de itens está vazia ou inválida.']);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmtCategoria = $this->pdo->prepare(
                'INSERT INTO categorias_itens (nome)
                 VALUES (:nome)
                 ON DUPLICATE KEY UPDATE id_categoria = LAST_INSERT_ID(id_categoria)'
            );
            $stmtItem = $this->pdo->prepare(
                'SELECT id_item FROM itens WHERE nome = :nome LIMIT 1 FOR UPDATE'
            );
            $stmtAtualizar = $this->pdo->prepare(
                'UPDATE itens
                    SET id_categoria = :id_categoria,
                        descricao = :descricao,
                        preco = :preco,
                        valor_hp = :valor_hp,
                        cura_status = :cura_status,
                        revive = :revive,
                        evolucao = 0
                  WHERE id_item = :id_item'
            );
            $stmtInserir = $this->pdo->prepare(
                'INSERT INTO itens
                    (id_categoria, nome, descricao, preco, valor_hp, cura_status, revive, evolucao)
                 VALUES
                    (:id_categoria, :nome, :descricao, :preco, :valor_hp, :cura_status, :revive, 0)'
            );

            $sincronizados = 0;
            foreach ($itensRecebidos as $itemRecebido) {
                if (!is_array($itemRecebido)) {
                    continue;
                }

                $slug = (string) ($itemRecebido['slug'] ?? '');
                if (!isset(self::ITENS[$slug])) {
                    continue;
                }

                $item = self::ITENS[$slug];
                $descricao = trim(strip_tags((string) ($itemRecebido['descricao'] ?? '')));
                $descricao = function_exists('mb_substr')
                    ? mb_substr($descricao, 0, 255, 'UTF-8')
                    : substr($descricao, 0, 255);

                $stmtCategoria->execute([':nome' => $item['categoria']]);
                $idCategoria = (int) $this->pdo->lastInsertId();

                $stmtItem->execute([':nome' => $item['nome']]);
                $idExistente = $stmtItem->fetchColumn();
                $valores = [
                    ':id_categoria' => $idCategoria,
                    ':descricao' => $descricao,
                    ':preco' => $item['preco'],
                    ':valor_hp' => $item['valor_hp'],
                    ':cura_status' => $item['cura_status'] ? 1 : 0,
                    ':revive' => $item['revive'] ? 1 : 0,
                ];

                if ($idExistente !== false) {
                    $stmtAtualizar->execute($valores + [':id_item' => (int) $idExistente]);
                } else {
                    $stmtInserir->execute($valores + [
                        ':nome' => $item['nome'],
                    ]);
                }

                $sincronizados++;
            }

            if ($sincronizados === 0) {
                throw new RuntimeException('Nenhum item válido foi recebido para sincronização.');
            }

            $this->pdo->commit();
            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => 'Itens sincronizados com o banco de dados.',
                'sincronizados' => $sincronizados,
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function comprar(): void
    {
        if (!$this->exigirLogin() || !$this->validarMetodo('POST') || !$this->validarCsrf()) {
            return;
        }

        $dados = $this->lerJson();
        if ($dados === null) {
            return;
        }
        $idItem = filter_var($dados['id_item'] ?? null, FILTER_VALIDATE_INT);
        $quantidade = filter_var($dados['quantidade'] ?? 1, FILTER_VALIDATE_INT);
        if ($idItem === false || $idItem < 1 || $quantidade === false || $quantidade < 1 || $quantidade > 99) {
            $this->responder(400, ['sucesso' => false, 'mensagem' => 'Item ou quantidade inválidos.']);
            return;
        }

        $idPerfil = Auth::idPerfil();
        try {
            $this->pdo->beginTransaction();

            $stmtItem = $this->pdo->prepare(
                'SELECT id_item, nome, preco
                   FROM itens
                  WHERE id_item = :id_item
                  FOR UPDATE'
            );
            $stmtItem->execute([':id_item' => $idItem]);
            $item = $stmtItem->fetch();
            if ($item === false) {
                $this->pdo->rollBack();
                $this->responder(404, ['sucesso' => false, 'mensagem' => 'Este item não está disponível na loja.']);
                return;
            }

            $total = (int) $item['preco'] * $quantidade;
            $stmtDebitar = $this->pdo->prepare(
                'UPDATE perfis
                    SET pokedollar = pokedollar - :total_debit
                  WHERE id_perfil = :id_perfil
                    AND pokedollar >= :minimum_balance'
            );
            $stmtDebitar->execute([
                ':total_debit' => $total,
                ':id_perfil' => $idPerfil,
                ':minimum_balance' => $total,
            ]);
            if ($stmtDebitar->rowCount() !== 1) {
                $saldo = $this->buscarSaldo($idPerfil);
                $this->pdo->rollBack();
                $this->responder(409, [
                    'sucesso' => false,
                    'mensagem' => 'Pokédollars insuficientes.',
                    'saldo' => $saldo,
                ]);
                return;
            }

            $stmtInventario = $this->pdo->prepare(
                'INSERT INTO inventarios (id_perfil, id_item, quantidade)
                 VALUES (:id_perfil, :id_item, :quantidade)
                 ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)'
            );
            $stmtInventario->execute([
                ':id_perfil' => $idPerfil,
                ':id_item' => $idItem,
                ':quantidade' => $quantidade,
            ]);

            $stmtCompra = $this->pdo->prepare(
                'INSERT INTO compras
                    (id_perfil, id_item, quantidade, valor_unitario, valor_total)
                 VALUES
                    (:id_perfil, :id_item, :quantidade, :valor_unitario, :valor_total)'
            );
            $stmtCompra->execute([
                ':id_perfil' => $idPerfil,
                ':id_item' => $idItem,
                ':quantidade' => $quantidade,
                ':valor_unitario' => (int) $item['preco'],
                ':valor_total' => $total,
            ]);

            $this->pdo->commit();
            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => sprintf('%s comprado(s) com sucesso.', $item['nome']),
                'saldo' => $this->buscarSaldo($idPerfil),
                'id_item' => $idItem,
                'quantidade' => $this->buscarQuantidade($idPerfil, $idItem),
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function exigirLogin(): bool
    {
        if (Auth::logado()) {
            return true;
        }

        $this->responder(401, ['sucesso' => false, 'mensagem' => 'Faça login para acessar a loja.']);
        return false;
    }

    private function validarMetodo(string $metodo): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === $metodo) {
            return true;
        }

        header('Allow: ' . $metodo);
        $this->responder(405, ['sucesso' => false, 'mensagem' => 'Método HTTP não permitido.']);
        return false;
    }

    private function validarCsrf(): bool
    {
        $recebido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $esperado = $_SESSION['csrf_loja'] ?? '';
        if ($recebido !== '' && $esperado !== '' && hash_equals($esperado, $recebido)) {
            return true;
        }

        $this->responder(403, ['sucesso' => false, 'mensagem' => 'Sessão da loja expirada. Atualize a página.']);
        return false;
    }

    private function lerJson(): ?array
    {
        $dados = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($dados)) {
            $this->responder(400, ['sucesso' => false, 'mensagem' => 'Corpo JSON inválido.']);
            return null;
        }
        return $dados;
    }

    private function buscarSaldo(int $idPerfil): int
    {
        $stmt = $this->pdo->prepare('SELECT pokedollar FROM perfis WHERE id_perfil = :id_perfil');
        $stmt->execute([':id_perfil' => $idPerfil]);
        $saldo = $stmt->fetchColumn();
        if ($saldo === false) {
            throw new RuntimeException('O perfil da sessão não foi encontrado.');
        }
        return (int) $saldo;
    }

    private function buscarQuantidade(int $idPerfil, int $idItem): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT quantidade FROM inventarios
              WHERE id_perfil = :id_perfil AND id_item = :id_item'
        );
        $stmt->execute([':id_perfil' => $idPerfil, ':id_item' => $idItem]);
        return (int) $stmt->fetchColumn();
    }

    private function responder(int $status, array $dados): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
