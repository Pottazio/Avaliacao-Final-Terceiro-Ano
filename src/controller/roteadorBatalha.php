<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use src\controller\BattleController;
use src\core\Auth;

if (!Auth::logado()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Sessão expirada. Faça login novamente.',
        'rota' => '../login/login.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'estado';
$controller = new BattleController();

match ($acao) {
    'estado' => $controller->estado(),
    'iniciar' => $controller->iniciar(),
    'jogar' => $controller->acao(),
    'fugir' => $controller->fugir(),
    default => (static function (): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação de batalha desconhecida.',
        ], JSON_UNESCAPED_UNICODE);
    })(),
};
