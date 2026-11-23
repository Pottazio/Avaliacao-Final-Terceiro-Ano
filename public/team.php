<?php
/**
 * POKENOAS - Team Builder (montagem de time).
 * Caminho do projeto: public/team.php
 *
 * Consumo de API: a PokeAPI e consultada pelo Time.js (fetch assincrono);
 * o PHP so recebe os atributos ja normalizados e grava no banco.
 */

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;
use models\ProgressaoJogo;

Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();
$progresso = ProgressaoJogo::carregar($pdo, Auth::idPerfil());
$nivelMaximo = $progresso['nivel_maximo'];

// Time do perfil (cria se ainda nao existir)
$stmt = $pdo->prepare('SELECT id_time, nome FROM times WHERE id_perfil = :id_perfil ORDER BY id_time LIMIT 1');
$stmt->execute([':id_perfil' => Auth::idPerfil()]);
$time = $stmt->fetch();

if ($time === false) {
    $ins = $pdo->prepare('INSERT INTO times (id_perfil, nome) VALUES (:id_perfil, :nome)');
    $ins->execute([':id_perfil' => Auth::idPerfil(), ':nome' => 'Meu Time']);
    $time = ['id_time' => (int) $pdo->lastInsertId(), 'nome' => 'Meu Time'];
}

// Saldo, para o cabecalho
$stmtSaldo = $pdo->prepare('SELECT pokedollar, nome_perfil FROM perfis WHERE id_perfil = :id_perfil');
$stmtSaldo->execute([':id_perfil' => Auth::idPerfil()]);
$perfil = $stmtSaldo->fetch() ?: ['pokedollar' => 0, 'nome_perfil' => 'Treinador'];

$nomeUsuario = Auth::nome();
$iniciais = '';
foreach (preg_split('/\s+/', trim($nomeUsuario)) as $parte) {
    if ($parte !== '' && mb_strlen($iniciais) < 2) {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
}
$iniciais = $iniciais !== '' ? $iniciais : '?';

$paleta = ['#d62828', '#1d3557', '#2a9d8f', '#f4a300', '#7b2cbf', '#e76f51'];
$corAvatar = $paleta[abs(crc32($nomeUsuario)) % count($paleta)];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Team Builder</title>
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/time.css">
</head>
<body class="jogo time-page" data-nivel-maximo="<?= $nivelMaximo ?>">

    <main class="painel">

        <!-- ---------------- CABECALHO ---------------- -->
        <header class="topo">
            <div class="topo-marca">
                <h1>POKENOAS</h1>
            </div>

            <div class="topo-treinador">
                <div class="avatar" style="background-color: <?= htmlspecialchars($corAvatar, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="avatar-iniciais"><?= htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="topo-dados">
                    <p class="topo-nome"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="topo-perfil">
                        Time: <?= htmlspecialchars($time['nome'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;|&nbsp; <span id="contadorTime">0 / 6</span>
                    </p>
                    <p class="saldo">
                        <span class="saldo-icone">$</span>
                        <span class="saldo-valor"><?= number_format((int) $perfil['pokedollar'], 0, ',', '.') ?></span>
                        <span class="saldo-rotulo">pokédollars</span>
                    </p>
                </div>
            </div>

            <a class="btn-sair" href="index.php">Voltar</a>
        </header>

        <p id="mensagemTime" class="login-msg"></p>

        <!-- ---------------- BUSCA NA POKEAPI ---------------- -->
        <section class="bloco">
            <h2 class="bloco-titulo">Buscar na Pokédex</h2>
            <p class="bloco-desc">
                Digite o nome ou o número do Pokémon. Os atributos vêm da PokeAPI.
                Seu limite atual é nível <?= $nivelMaximo ?>.
            </p>

            <form id="formBusca" class="busca-form" autocomplete="off">
                <input type="text" id="campoPokemon" name="nome"
                       placeholder="pikachu, charmander, 25..."
                       autocomplete="off" required
                       role="combobox" aria-autocomplete="list"
                       aria-controls="sugestoesPokemon" aria-expanded="false">
                <button type="submit">Pesquisar</button>
                <div id="sugestoesPokemon" class="sugestoes-pokemon"
                     role="listbox" aria-label="Sugestões da primeira geração" hidden></div>
            </form>

            <div id="resultadoBusca" class="resultado-busca"></div>
        </section>

        <!-- ---------------- TIME ATUAL ---------------- -->
        <section class="bloco">
            <h2 class="bloco-titulo">Meu time</h2>

            <div id="listaTime" class="grade-time">
                <p class="time-vazio">Carregando o time...</p>
            </div>
        </section>

    </main>

    <script src="JavaScript/Time.js"></script>
</body>
</html>
