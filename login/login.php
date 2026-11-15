<?php
/**
 * MONSTROBOLSO - Tela de login (View do MVC).
 * View só apresenta; quem decide é o LoginController.
 * Caminho do projeto: login/login.php
 */

require_once __DIR__ . '/../bootstrap.php';

use src\core\Auth;

// Já logado? Vai direto para o jogo.
if (Auth::logado()) {
    header('Location: ../public/index.php');
    exit;
}

$erro = $_SESSION['erro_login'] ?? null;
unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONSTROBOLSO | Login</title>
    <link rel="stylesheet" href="../public/CSS/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <div class="login-brand">
            <h1>MONSTROBOLSO</h1>
            <p>Entrar no jogo</p>
        </div>

        <section class="login-card">
            <!-- ABA DE ENTRAR -->
            <div id="telaLogin">
                <h2>Acesse sua conta</h2>

                <?php if ($erro): ?>
                    <p class="login-error" role="alert">
                        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <p id="mensagemLogin" class="login-msg"></p>

                <form id="formLogin" class="login-form" method="POST">
                    <label for="loginUsuario">E-mail</label>
                    <input type="email" id="loginUsuario" name="email"
                           autocomplete="email" required>

                    <label for="loginSenha">Senha</label>
                    <input type="password" id="loginSenha" name="senha"
                           autocomplete="current-password" required>

                    <button type="submit">Entrar</button>
                </form>

                <p class="texto-cadastro">
                    Não tem conta?
                    <a href="#" id="mostrarCadastro">Cadastre-se</a>
                </p>
            </div>

            <!-- ABA DE CADASTRAR -->
            <div id="telaCadastro" style="display:none">
                <h2>Criar conta</h2>

                <form id="formCadastro" class="login-form" method="POST">
                    <label for="cadNome">Nome</label>
                    <input type="text" id="cadNome" name="nome"
                           autocomplete="name" required>

                    <label for="cadEmail">E-mail</label>
                    <input type="email" id="cadEmail" name="email"
                           autocomplete="email" required>

                    <label for="cadSenha">Senha (mín. 6 caracteres)</label>
                    <input type="password" id="cadSenha" name="senha"
                           autocomplete="new-password" minlength="6" required>

                    <button type="submit">Cadastrar</button>
                </form>

                <p class="texto-cadastro">
                    Já tem conta?
                    <a href="#" id="voltarLogin">Voltar ao login</a>
                </p>
            </div>
        </section>
    </main>

    <script src="../public/JavaScript/Login.js"></script>
</body>
</html>
