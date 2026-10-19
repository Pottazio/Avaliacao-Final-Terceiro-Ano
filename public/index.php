
<?php

session_start();

require_once __DIR__ . "/../src/controller/LoginController.php";

$controller = new LoginController();

$acao = $_GET["acao"] ?? "login";

switch ($acao) {
    case "login":
        $controller->login();
        break;

    case "autenticar":
        $controller->autenticar();
        break;

    case "painel":
        $controller->painel();
        break;

    case "logout":
        $controller->logout();
        break;

    default:
        http_response_code(404);
        echo "Página não encontrada.";
        break;
}
