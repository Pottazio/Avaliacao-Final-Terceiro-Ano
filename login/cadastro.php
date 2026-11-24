<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;

if (Auth::logado()) {
    header('Location: ../public/index.php');
    exit;
}

$erro = $_SESSION['erro_cadastro'] ?? null;
unset($_SESSION['erro_cadastro']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Cadastro</title>
    <link rel="stylesheet" href="../public/CSS/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <div class="login-brand">
            <h1>POKENOAS</h1>
            <p>Crie sua conta de treinador</p>
        </div>

        <section class="login-card">
            <h2>Criar conta</h2>

            <?php if ($erro): ?>
                <p class="login-error" role="alert">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <p id="mensagemCadastro" class="login-msg"></p>

            <form id="formCadastro" class="login-form" method="POST"
                  action="../src/controller/roteadorLogin.php?acao=cadastrar">
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
                <a href="login.php">Voltar ao login</a>
            </p>
        </section>
    </main>

    <script src="../public/JavaScript/login.js"></script>
</body>
</html>
