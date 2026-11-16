<?php
/**
 * POKENOAS - Painel do treinador (área logada).
 * Caminho do projeto: public/index.php
 *
 * Mostra o avatar, os dados do perfil e o menu do jogo.
 */

require_once __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;

// Não logado -> volta para o login.
Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();

$stmt = $pdo->prepare(
    'SELECT p.id_perfil,
            p.nome_perfil,
            p.pokedollar,
            u.nome  AS nome_usuario,
            u.email,
            u.criado_em
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

/* ---------------------------------------------------------------
   Avatar: iniciais do treinador, geradas a partir do nome.
   Sem upload e sem coluna nova no banco.
   --------------------------------------------------------------- */
$nomeUsuario = (string) $perfil['nome_usuario'];

$iniciais = '';
foreach (preg_split('/\s+/', trim($nomeUsuario)) as $parte) {
    if ($parte !== '' && mb_strlen($iniciais) < 2) {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
}
if ($iniciais === '') {
    $iniciais = '?';
}

/* Cor do avatar derivada do nome: sempre a mesma para o mesmo treinador. */
$paleta = ['#d62828', '#1d3557', '#2a9d8f', '#f4a300', '#7b2cbf', '#e76f51'];
$corAvatar = $paleta[abs(crc32($nomeUsuario)) % count($paleta)];

/* Quantos Pokemon e quantas batalhas o perfil tem (para os cartões). */
$stmtQtd = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM time_pokemons tp
           JOIN times t ON t.id_time = tp.id_time
          WHERE t.id_perfil = :p1) AS qtd_pokemon,
        (SELECT COUNT(*) FROM batalhas WHERE id_perfil = :p2) AS qtd_batalha,
        (SELECT SUM(CASE WHEN resultado = "VITORIA" THEN 1 ELSE 0 END)
           FROM batalhas WHERE id_perfil = :p3) AS qtd_vitoria'
);
$stmtQtd->execute([
    ':p1' => Auth::idPerfil(),
    ':p2' => Auth::idPerfil(),
    ':p3' => Auth::idPerfil(),
]);
$qtd = $stmtQtd->fetch() ?: [];

$qtdPokemon = (int) ($qtd['qtd_pokemon'] ?? 0);
$qtdBatalha = (int) ($qtd['qtd_batalha'] ?? 0);
$qtdVitoria = (int) ($qtd['qtd_vitoria'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Painel do Treinador</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body class="jogo">

    <main class="painel">

        <!-- ---------------- CABEÇALHO ---------------- -->
        <header class="topo">
            <div class="topo-marca">
                <h1>POKENOAS</h1>
            </div>

            <div class="topo-treinador">
                <!-- Avatar com as iniciais do treinador -->
                <div class="avatar" style="background-color: <?= htmlspecialchars($corAvatar, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="avatar-iniciais"><?= htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="topo-dados">
                    <p class="topo-nome"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="topo-perfil">
                        Perfil: <?= htmlspecialchars($perfil['nome_perfil'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p class="saldo">
                        <span class="saldo-icone">$</span>
                        <span class="saldo-valor"><?= number_format((int) $perfil['pokedollar'], 0, ',', '.') ?></span>
                        <span class="saldo-rotulo">pokédollars</span>
                    </p>
                </div>
            </div>

            <a class="btn-sair" href="../src/controller/roteadorLogin.php?acao=sair">Sair</a>
        </header>

        <!-- ---------------- ESTATÍSTICAS ---------------- -->
        <section class="stats">
            <div class="stat">
                <span class="stat-valor"><?= $qtdPokemon ?></span>
                <span class="stat-rotulo">no time</span>
            </div>
            <div class="stat">
                <span class="stat-valor"><?= $qtdVitoria ?></span>
                <span class="stat-rotulo">vitórias</span>
            </div>
            <div class="stat">
                <span class="stat-valor"><?= $qtdBatalha ?></span>
                <span class="stat-rotulo">batalhas</span>
            </div>
        </section>

        <!-- ---------------- MENU ---------------- -->
        <nav class="menu">
            <a class="card-menu card-time" href="team.php">
                <span class="card-titulo">Montar meu time</span>
                <span class="card-desc">Escolha seus Pokémon e monte a equipe</span>
            </a>

            <a class="card-menu card-batalha" href="battle.php">
                <span class="card-titulo">Ir para batalha</span>
                <span class="card-desc">Enfrente um oponente e ganhe pokédollars</span>
            </a>

            <a class="card-menu card-loja" href="shop.php">
                <span class="card-titulo">Poké Mart</span>
                <span class="card-desc">Poções, cura de status e pedras de evolução</span>
            </a>

            <a class="card-menu card-perfil" href="perfil.php">
                <span class="card-titulo">Meus perfis</span>
                <span class="card-desc">Troque de perfil ou crie um novo treinador</span>
            </a>
        </nav>

        <footer class="rodape">
            <p>Treinador desde <?= htmlspecialchars(date('d/m/Y', strtotime((string) $perfil['criado_em'])), ENT_QUOTES, 'UTF-8') ?></p>
        </footer>

    </main>

</body>
</html>
