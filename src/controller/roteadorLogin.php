<?php
declare(strict_types=1);

/**
 * Front controller do login.
 * Aceita POST ?acao=entrar | cadastrar | trocarPerfil | sair
 * e também chamadas JSON via fetch do Login.js.
 */

require_once __DIR__ . '/../../bootstrap.php';

use src\controller\LoginController;

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'entrar';

$controller = new LoginController();

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
