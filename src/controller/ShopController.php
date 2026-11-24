<?php
declare(strict_types=1);

namespace src\controller;

use PDO;
use RuntimeException;
use src\core\Auth;
use src\dao\LojaCatalogoDAO;

class ShopController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(): void
    {
        if (!$this->exigirLogin()) {
            return;
        }

        (new LojaCatalogoDAO($this->pdo))->garantirCatalogo();

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

        $inseridos = (new LojaCatalogoDAO($this->pdo))->garantirCatalogo();
        $this->responder(200, [
            'sucesso' => true,
            'mensagem' => $inseridos > 0
                ? 'Itens inseridos no banco de dados local.'
                : 'O catálogo local já está completo.',
            'inseridos' => $inseridos,
        ]);
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
