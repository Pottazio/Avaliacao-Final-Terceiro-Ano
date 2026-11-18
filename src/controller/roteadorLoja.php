<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use config\Database;
use src\controller\ShopController;

header('Cache-Control: no-store');

try {
    $controller = new ShopController(Database::conectar());
    $acao = (string) ($_GET['acao'] ?? '');

    switch ($acao) {
        case 'listar':
            $controller->listar();
            break;
        case 'sincronizar':
            $controller->sincronizar();
            break;
        case 'comprar':
            $controller->comprar();
            break;
        default:
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['sucesso' => false, 'mensagem' => 'Ação da loja inválida.']);
    }
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível concluir a operação da loja.',
    ], JSON_UNESCAPED_UNICODE);
}
