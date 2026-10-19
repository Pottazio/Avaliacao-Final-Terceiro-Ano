
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONSTROBOLSO | Login</title>
</head>
<body>

    <h1>MONSTROBOLSO</h1>
    <h2>Entrar no jogo</h2>

    <?php if (!empty($_SESSION["erro_login"])): ?>
        <p>
            <?= htmlspecialchars($_SESSION["erro_login"], ENT_QUOTES, "UTF-8") ?>
        </p>
        <?php unset($_SESSION["erro_login"]); ?>
    <?php endif; ?>

    <form action="index.php?acao=autenticar" method="POST">
        <label for="email">E-mail:</label>
        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <br><br>

        <label for="senha">Senha:</label>
        <input
            type="password"
            id="senha"
            name="senha"
            required
        >

        <br><br>

        <button type="submit">Entrar</button>
    </form>

</body>
</html>
