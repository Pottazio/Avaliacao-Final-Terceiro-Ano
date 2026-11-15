<?php
/**
 * Área logada de exemplo: mostra nome, perfil ativo e saldo.
 * Caminho do projeto: public/index.php
 * Serve de base para public/team.php, public/battle.php e public/shop.php.
 */

require_once __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;

// Não logado -> volta para o login.
Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();

$stmt = $pdo->prepare(
    'SELECT p.id_perfil, p.nome_perfil, p.pokedollar, u.nome AS nome_usuario, u.email
       FROM perfis p
       JOIN usuarios u ON u.id_usuario = p.id_usuario
      WHERE p.id_perfil = :id_perfil'
);
$stmt->execute([':id_perfil' => Auth::idPerfil()]);
$perfil = $stmt->fetch();

// Sessão aponta para um perfil que não existe mais: limpa e volta.
if ($perfil === false) {
    Auth::deslogar();
    header('Location: ../login/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>MONSTROBOLSO | Início</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
    <header class="topo">
        <h1>MONSTROBOLSO</h1>
        <p>Olá, <strong><?= htmlspecialchars($perfil['nome_usuario'], ENT_QUOTES, 'UTF-8') ?></strong>
           — perfil: <?= htmlspecialchars($perfil['nome_perfil'], ENT_QUOTES, 'UTF-8') ?></p>
        <p>Pokédollars: <strong><?= (int) $perfil['pokedollar'] ?></strong></p>
        <a href="../src/controller/roteadorLogin.php?acao=sair">Sair</a>
    </header>

    <nav class="menu">
        <a href="team.php">Montar meu time</a>
        <a href="battle.php">Ir para batalha</a>
        <a href="shop.php">Visitar Poké Mart</a>
    </nav>
</body>
</html>

