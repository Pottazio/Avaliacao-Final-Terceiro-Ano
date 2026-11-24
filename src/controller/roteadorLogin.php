<?php
declare(strict_types=1);

/**
 * Front controller do login.
 * Aceita POST ?acao=entrar | cadastrar | trocarPerfil | sair
 * e também chamadas JSON via fetch do Login.js.
 */

require __DIR__ . '/../../bootstrap.php';

use src\controller\LoginController;
use config\ConexaoException;

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'entrar';

try {
    $controller = new LoginController();
} catch (ConexaoException $e) {
    error_log('[Login] ' . $e->getMessage());
    $mensagem = 'Não foi possível conectar ao banco. Confira se o MySQL está ativo e se as credenciais estão corretas.';
    $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if (!$ajax) {
        $_SESSION['erro_login'] = $mensagem;
        header('Location: ../../login/login.php');
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $mensagem,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

match ($acao) {
    'entrar'       => $controller->entrar(),
    'cadastrar'    => $controller->cadastrar(),
    'trocarPerfil' => $controller->trocarPerfil(),
    'sair'         => $controller->sair(),
    default        => (static function (): void {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Ação desconhecida.']);
    })(),
};
