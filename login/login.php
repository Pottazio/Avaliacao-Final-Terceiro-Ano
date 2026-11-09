
<?php
session_start();
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
            <h2>Acesse sua conta</h2>

            <?php if (!empty($_SESSION["erro_login"])): ?>
                <p class="login-error" role="alert">
                    <?= htmlspecialchars($_SESSION["erro_login"], ENT_QUOTES, "UTF-8") ?>
                </p>
                <?php unset($_SESSION["erro_login"]); ?>
            <?php endif; ?>

            <form class="login-form" action="../src/controller/LoginController.php" method="POST">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" autocomplete="email" required>

                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" autocomplete="current-password" required>

                <button type="submit">Entrar</button>
            </form>
        </section>
    </main>
</body>
</html>
