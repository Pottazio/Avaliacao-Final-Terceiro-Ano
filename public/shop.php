<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;

Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();
$stmt = $pdo->prepare('SELECT nome_perfil, genero, pokedollar FROM perfis WHERE id_perfil = :id_perfil');
$stmt->execute([':id_perfil' => Auth::idPerfil()]);
$perfil = $stmt->fetch() ?: ['nome_perfil' => Auth::nome(), 'genero' => 'masculino', 'pokedollar' => 0];

$imagemPersonagem = $perfil['genero'] === 'feminino'
    ? 'PersonagemFemiPequeno.png'
    : 'PersonagemMascPequeno.png';

$paleta = ['#d62828', '#1d3557', '#2a9d8f', '#f4a300', '#7b2cbf', '#e76f51'];
$corAvatar = $paleta[abs(crc32((string) $perfil['nome_perfil'])) % count($paleta)];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Poké Mart</title>
    <link rel="stylesheet" href="CSS/style.css?v=<?= (int) filemtime(__DIR__ . '/CSS/style.css') ?>">
</head>
<body class="jogo">
    <main class="painel">
        <header class="topo">
            <div class="topo-marca"><h1>POKENOAS</h1></div>
            <div class="topo-treinador">
                <div class="avatar" style="background-color: <?= htmlspecialchars($corAvatar, ENT_QUOTES, 'UTF-8') ?>">
                    <img class="avatar-imagem" src="assets/<?= htmlspecialchars($imagemPersonagem, ENT_QUOTES, 'UTF-8') ?>" alt="Personagem do treinador">
                </div>
                <div class="topo-dados">
                    <p class="topo-nome"><?= htmlspecialchars((string) $perfil['nome_perfil'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="topo-perfil">Poké Mart</p>
                    <p class="saldo">
                        <span class="saldo-icone">$</span>
                        <span class="saldo-valor" id="saldoHeader"><?= number_format((int) $perfil['pokedollar'], 0, ',', '.') ?></span>
                        <span class="saldo-rotulo">pokédollars</span>
                    </p>
                </div>
            </div>
            <a class="btn-sair" href="index.php">Voltar</a>
        </header>

        <section class="pagina">
            <h2>Poké Mart</h2>
            <div class="saldo"><h3>Pokédollars: <span id="saldoPokemon"><?= number_format((int) $perfil['pokedollar'], 0, ',', '.') ?></span></h3></div>
            <div id="listaItensLoja" class="loja-categorias"><p>Carregando itens da PokeAPI...</p></div>
            <p id="mensagemLoja"></p>
        </section>
    </main>

    <script type="module" src="JavaScript/shop-page.js?v=<?= (int) filemtime(__DIR__ . '/JavaScript/shop-page.js') ?>"></script>
</body>
</html>
