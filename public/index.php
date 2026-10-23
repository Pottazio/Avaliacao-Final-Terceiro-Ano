
<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login/login.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MonstroBolso</title>

    <link rel="stylesheet" href="CSS/style.css">

</head>

<body>

    <header>

        <h1>MonstroBolso</h1>

        <nav>

            <button onclick="navegar('index')">
                Início
            </button>

            <button onclick="navegar('team')">
                Team Builder
            </button>

            <button onclick="navegar('battle')">
                Batalha
            </button>

            <button onclick="navegar('shop')">
                Poké Mart
            </button>

            <button onclick="window.location.href='../login/login.php'">
                Login
            </button>

        </nav>

    </header>


    <main id="conteudo">

    </main>


    <footer>

        <p>
            MonstroBolso © 2026
        </p>

    </footer>


    <script src="JavaScript/java.js"></script>

</body>

</html>