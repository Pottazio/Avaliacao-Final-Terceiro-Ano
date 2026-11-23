<?php
declare(strict_types=1);

/**
 * POKENOAS - Front controller do Team Builder.
 * Caminho do projeto: src/controller/roteadorTime.php
 *
 * Aceita ?acao=buscar | adicionar | remover | atualizarNivel | listar
 * e responde sempre em JSON (requisito do trabalho).
 */

require __DIR__ . '/../../bootstrap.php';

use src\core\Auth;
use src\controller\TeamController;

// Sem sessão não há time: devolve JSON, não redireciona.
if (!Auth::logado()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'sucesso'  => false,
        'mensagem' => 'Sessão expirada. Faça login novamente.',
        'rota'     => '../login/login.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'listar';

$controller = new TeamController();

match ($acao) {
    'buscar'    => $controller->buscar(),
    'adicionar' => $controller->adicionar(),
    'remover'   => $controller->remover(),
    'atualizarNivel' => $controller->atualizarNivel(),
    'listar'    => $controller->listar(),
    default     => (static function (): void {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso'  => false,
            'mensagem' => 'Ação desconhecida.',
        ], JSON_UNESCAPED_UNICODE);
    })(),
};
