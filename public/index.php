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

if (empty($_SESSION['csrf_personagem'])) {
    $_SESSION['csrf_personagem'] = bin2hex(random_bytes(32));
}

$generoPersonagem = $_SESSION['genero_personagem'] ?? 'masculino';
$erroPersonagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_personagem'], $token)) {
        http_response_code(403);
        $erroPersonagem = 'A solicitação expirou. Atualize a página e tente novamente.';
    } else {
        $novoGenero = (string) ($_POST['genero_personagem'] ?? '');

        if (!in_array($novoGenero, ['masculino', 'feminino'], true)) {
            http_response_code(400);
            $erroPersonagem = 'Seleção de personagem inválida.';
        } else {
            $_SESSION['genero_personagem'] = $novoGenero;
            header('Location: index.php');
            exit;
        }
    }
}

$imagemPersonagem = $generoPersonagem === 'feminino'
    ? 'PersonagemFemiGrande.png'
    : 'PersonagemMascGrande.png';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONSTROBOLSO | Início</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body class="menu-principal-page">
    <main class="menu-principal">
        <div class="menu-principal-coluna">
            <header class="menu-principal-titulo">
                <h1>POKENOAS</h1>
            </header>

            <nav class="menu-principal-navegacao" aria-label="Menu do jogo">
                <a href="html/team.html">Montar meu time</a>
                <a href="html/battle.html">Ir para batalha</a>
                <a href="shop.php">Visitar Poké Mart</a>
            </nav>

            <a class="menu-principal-sair" href="../src/controller/roteadorLogin.php?acao=sair">
                Sair
            </a>
        </div>

        <aside class="menu-principal-jogador" aria-labelledby="titulo-jogador">
            <h2 id="titulo-jogador">Informações do jogador</h2>
            <img class="menu-principal-personagem"
                 src="assets/<?= htmlspecialchars($imagemPersonagem, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Personagem <?= htmlspecialchars($generoPersonagem, ENT_QUOTES, 'UTF-8') ?>">

            <form class="menu-principal-genero" method="POST" action="index.php">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_personagem'], ENT_QUOTES, 'UTF-8') ?>">
                <span>Personagem</span>
                <div class="menu-principal-genero-opcoes">
                    <button type="submit" name="genero_personagem" value="masculino"
                            aria-pressed="<?= $generoPersonagem === 'masculino' ? 'true' : 'false' ?>"
                            class="<?= $generoPersonagem === 'masculino' ? 'selecionado' : '' ?>">
                        Masculino
                    </button>
                    <button type="submit" name="genero_personagem" value="feminino"
                            aria-pressed="<?= $generoPersonagem === 'feminino' ? 'true' : 'false' ?>"
                            class="<?= $generoPersonagem === 'feminino' ? 'selecionado' : '' ?>">
                        Feminino
                    </button>
                </div>
            </form>

            <?php if ($erroPersonagem): ?>
                <p class="menu-principal-erro" role="alert">
                    <?= htmlspecialchars($erroPersonagem, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <div class="menu-principal-dados">
                <p>
                    <span>Nome</span>
                    <strong><?= htmlspecialchars($perfil['nome_usuario'], ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
                <p>
                    <span>Pokédollars</span>
                    <strong><?= number_format((int) $perfil['pokedollar'], 0, ',', '.') ?></strong>
                </p>
            </div>
        </aside>
    </main>
</body>
</html>

