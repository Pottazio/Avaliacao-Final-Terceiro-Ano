<?php
/**
 * POKENOAS - Tela de login (View do MVC).
 * View só apresenta; quem decide é o LoginController.
 * Caminho do projeto: login/login.php
 *
 * IMPORTANTE: os id/name dos campos abaixo são usados pelo login.js.
 * Ao mexer no visual, mude apenas classes e estrutura, nunca os id/name.
 */

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;


if (Auth::logado()) {
    header('Location: ../public/index.php');
    exit;
}

$erro = $_SESSION['erro_login'] ?? null;
unset($_SESSION['erro_login']);
$sucessoCadastro = $_SESSION['sucesso_cadastro'] ?? null;
unset($_SESSION['sucesso_cadastro']);

// Diagnóstico: avisa em tela se algum arquivo essencial não carregou.
$avisos = [];
foreach ([
    'bootstrap.php'                    => __DIR__ . '/../bootstrap.php',
    'src/core/Autoload.php'            => __DIR__ . '/../src/core/Autoload.php',
    'src/core/Auth.php'                => __DIR__ . '/../src/core/Auth.php',
    'models/Usuario.php'               => __DIR__ . '/../models/Usuario.php',
    'src/controller/roteadorLogin.php' => __DIR__ . '/../src/controller/roteadorLogin.php',
    'config/DataBase.php'              => __DIR__ . '/../config/DataBase.php',
] as $rotulo => $caminho) {
    if (!is_file($caminho)) {
        $avisos[] = "Arquivo ausente: {$rotulo}";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Login</title>
    <link rel="stylesheet" href="../public/CSS/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <div class="login-brand">
            <h1>POKENOAS</h1>
            <p>Entre no jogo</p>
        </div>

        <?php if ($avisos): ?>
            <div class="login-error" role="alert">
                <strong>Faltam arquivos no projeto:</strong>
                <?php foreach ($avisos as $aviso): ?>
                    <p><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="login-card">
            <h2>Acesse sua conta</h2>

            <?php if ($sucessoCadastro): ?>
                <p class="login-msg" data-tipo="ok" role="status">
                    <?= htmlspecialchars($sucessoCadastro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if ($erro): ?>
                <p class="login-error" role="alert">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <p id="mensagemLogin" class="login-msg" role="status"></p>

            <form id="formLogin" class="login-form" method="POST"
                  action="../src/controller/roteadorLogin.php?acao=entrar">
                <label for="loginUsuario">E-mail</label>
                <input type="email" id="loginUsuario" name="email"
                       placeholder="treinador@pokenoas.com"
                       autocomplete="email" required>

                <label for="loginSenha">Senha</label>
                <input type="password" id="loginSenha" name="senha"
                       placeholder="••••••"
                       autocomplete="current-password" required>

                <button type="submit">Entrar</button>
            </form>

            <p class="texto-cadastro">
                Não tem conta?
                <a href="cadastro.php">Cadastre-se</a>
            </p>
        </section>
    </main>

    <script src="../public/JavaScript/login.js"></script>
</body>
</html>
