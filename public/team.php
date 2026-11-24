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
$stmtSaldo = $pdo->prepare('SELECT pokedollar, nome_perfil, genero FROM perfis WHERE id_perfil = :id_perfil');
$stmtSaldo->execute([':id_perfil' => Auth::idPerfil()]);
$perfil = $stmtSaldo->fetch() ?: ['pokedollar' => 0, 'nome_perfil' => 'Treinador'];

$nomeUsuario = (string) ($perfil['nome_perfil'] ?? Auth::nome());
$generoPersonagem = $perfil['genero'] ?? 'masculino';
$imagemPersonagem = $generoPersonagem === 'feminino'
    ? 'PersonagemFemiPequeno.png'
    : 'PersonagemMascPequeno.png';

$paleta = ['#d62828', '#1d3557', '#2a9d8f', '#f4a300', '#7b2cbf', '#e76f51'];
$corAvatar = $paleta[abs(crc32($nomeUsuario)) % count($paleta)];

$stmtBatalha = $pdo->prepare("SELECT 1 FROM batalhas WHERE id_perfil = :id_perfil AND resultado = 'EM_ANDAMENTO' LIMIT 1");
$stmtBatalha->execute([':id_perfil' => Auth::idPerfil()]);
$emBatalha = $stmtBatalha->fetchColumn() !== false;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Team Builder</title>
    <link rel="stylesheet" href="CSS/style.css?v=<?= (int) filemtime(__DIR__ . '/CSS/style.css') ?>">
    <link rel="stylesheet" href="CSS/time.css?v=<?= (int) filemtime(__DIR__ . '/CSS/time.css') ?>">
</head>
<body class="jogo time-page" data-nivel-maximo="<?= $nivelMaximo ?>" data-batalha-em-andamento="<?= $emBatalha ? '1' : '0' ?>">

    <main class="painel">

        <!-- ---------------- CABECALHO ---------------- -->
        <header class="topo">
            <div class="topo-marca">
                <h1>POKENOAS</h1>
            </div>

            <div class="topo-treinador">
                <div class="avatar" style="background-color: <?= htmlspecialchars($corAvatar, ENT_QUOTES, 'UTF-8') ?>">
                    <img
                        class="avatar-imagem"
                        src="assets/<?= htmlspecialchars($imagemPersonagem, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Personagem do treinador"
                    >
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
        <?php if ($emBatalha): ?>
            <p class="login-msg" data-tipo="info" role="alert">
                Uma batalha está em andamento. Volte para a batalha e a encerre antes de modificar o time.
            </p>
        <?php endif; ?>

        <!-- ---------------- BUSCA NA POKEAPI ---------------- -->
        <section class="bloco">
            <h2 class="bloco-titulo">Buscar na Pokédex</h2>
            <p class="bloco-desc">
                Digite o nome ou o número do Pokémon. Os atributos vêm da PokeAPI.
            </p>

            <form id="formBusca" class="busca-form">
                <input type="text" id="campoPokemon" name="nome"
                       placeholder="pikachu, charmander, 25..."
                       autocomplete="off" required>
                <button type="submit">Pesquisar</button>
            </form>

            <div id="resultadoBusca" class="resultado-busca"></div>
        </section>

        <!-- ---------------- TIME ATUAL ---------------- -->
        <section class="bloco">
            <h2 class="bloco-titulo">Meu time</h2>
            <p class="bloco-desc">
                Sua equipe pode ter até seis Pokémon. Edite até quatro ataques em cada cartão ou mantenha a seleção automática.
            </p>

            <div id="listaTime" class="grade-time">
                <p class="time-vazio">Carregando o time...</p>
            </div>
        </section>

        <!-- ---------------- ESCOLHA DE POKÉMON ---------------- -->
        <section class="bloco">
            <h2 class="bloco-titulo">Pokédex desbloqueada</h2>
            <p class="bloco-desc">
                Selecione um Pokémon liberado para ver os detalhes e adicioná-lo ao time.
            </p>

            <div class="pokedex-desbloqueada">
                <p id="resumoProgresso" class="bloco-desc">Carregando Pokédex liberada...</p>
                <div id="galeriaPokemon" class="grade-pokedex" aria-live="polite">
                    <p class="time-vazio">Carregando Pokémon...</p>
                </div>
            </div>

            <div id="resultadoBusca" class="resultado-busca"></div>
        </section>

    </main>

    <dialog id="modalMovePool" class="modal-movepool" aria-labelledby="modalMoveTitulo">
        <form method="dialog" class="modal-movepool-conteudo">
            <header class="modal-movepool-cabecalho">
                <div>
                    <p class="modal-movepool-etiqueta">Configuração de batalha</p>
                    <h2 id="modalMoveTitulo">Editar Pokémon</h2>
                </div>
                <button class="modal-movepool-fechar" value="cancel" aria-label="Fechar">×</button>
            </header>
            <p id="modalMoveDescricao" class="modal-movepool-descricao"></p>
            <label class="modal-movepool-nivel" for="nivelPokemonEditado">
                Nível
                <select id="nivelPokemonEditado"></select>
            </label>
            <label class="modal-movepool-shiny" for="shinyPokemonEditado">
                <input id="shinyPokemonEditado" type="checkbox">
                <span>Usar aparência Shiny</span>
            </label>
            <p id="modalMoveContagem" class="modal-movepool-contagem">0 / 4 selecionados</p>
            <div id="modalMoveLista" class="modal-movepool-lista" aria-live="polite"></div>
            <p id="modalMoveMensagem" class="modal-movepool-mensagem" aria-live="polite"></p>
            <footer class="modal-movepool-acoes">
                <button class="modal-movepool-cancelar" value="cancel">Cancelar</button>
                <button id="salvarMovePool" class="modal-movepool-salvar" type="button" disabled>
                    Salvar Pokémon
                </button>
            </footer>
        </form>
    </dialog>

    <script src="JavaScript/Time.js?v=<?= (int) filemtime(__DIR__ . '/JavaScript/Time.js') ?>"></script>
</body>
</html>
