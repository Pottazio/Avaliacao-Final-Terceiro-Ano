<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;

Auth::exigirLogin('../login/login.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Batalha</title>
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/battle.css?v=<?= filemtime(__DIR__ . '/CSS/battle.css') ?>">
</head>
<body class="jogo battle-page">
    <main class="painel">
        <header class="topo">
            <div class="topo-marca"><h1>POKENOAS</h1></div>
            <div class="topo-treinador">
                <div class="topo-dados">
                    <p class="topo-nome"><?= htmlspecialchars(Auth::nome(), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="topo-perfil">Jornada e batalhas</p>
                </div>
            </div>
            <a class="btn-sair" href="index.php">Voltar</a>
        </header>

        <div id="mensagemBatalha" class="battle-message" role="status" aria-live="polite" hidden></div>

        <div class="battle-layout">
            <div class="battle-main">
                <section class="battle-panel progression-panel" aria-labelledby="progression-title">
                    <div class="progression-heading">
                        <div>
                            <h2 id="progression-title">Sua jornada</h2>
                            <p id="progressoTexto">Carregando progresso...</p>
                        </div>
                        <p class="level-cap">Nível máximo <strong id="limiteNivel">—</strong></p>
                    </div>
                    <progress id="barraProgresso" max="1" value="0">0%</progress>
                    <ol id="listaEtapas" class="stage-list"></ol>
                </section>

                <section class="battle-panel setup-panel" aria-labelledby="battle-title">
                    <div>
                        <h2 id="battle-title">Próximo desafio</h2>
                        <p id="desafioTexto">Carregando próximo treinador...</p>
                        <p id="avisoEquipe" class="team-warning" hidden>
                            Adicione Pokémon à sua equipe em <a href="team.php">Montar meu time</a>.
                        </p>
                    </div>
                    <div class="battle-setup">
                        <label for="pokemonEscolhido">Pokémon da equipe</label>
                        <select id="pokemonEscolhido"></select>
                        <button id="iniciarBatalha" class="btn" type="button" disabled>Iniciar batalha</button>
                    </div>
                </section>

                <section class="arena-panel" aria-label="Arena de batalha">
                    <article class="fighter-card player-card">
                        <div class="fighter-details">
                            <p id="jogadorNome" class="fighter-name">Seu Pokémon</p>
                            <p id="jogadorNivel" class="fighter-level"></p>
                            <p id="jogadorStatus" class="fighter-status"></p>
                            <progress id="jogadorHpBar" max="1" value="0"></progress>
                            <p id="jogadorHpTexto" class="hp-text">—</p>
                        </div>
                        <img id="jogadorSprite" class="fighter-sprite player-sprite"
                             alt="" width="192" height="192" hidden>
                    </article>
                    <article class="fighter-card opponent-card">
                        <div class="fighter-details">
                            <p id="oponenteTreinador" class="trainer-name"></p>
                            <p id="oponenteNome" class="fighter-name">Oponente</p>
                            <p id="oponenteNivel" class="fighter-level"></p>
                            <p id="oponenteStatus" class="fighter-status"></p>
                            <progress id="oponenteHpBar" max="1" value="0"></progress>
                            <p id="oponenteHpTexto" class="hp-text">—</p>
                            <div id="equipeOponente" class="opponent-roster"
                                 aria-label="Equipe do treinador adversário"></div>
                        </div>
                        <img id="oponenteSprite" class="fighter-sprite opponent-sprite"
                             alt="" width="160" height="160" hidden>
                    </article>
                </section>

                <section id="decisaoTrocaOponente" class="battle-panel switch-decision-panel"
                         aria-labelledby="decisaoTrocaTitulo" aria-live="polite" hidden>
                    <h2 id="decisaoTrocaTitulo">Próximo Pokémon do treinador</h2>
                    <p id="decisaoTrocaTexto"></p>
                    <label for="selecaoTrocaOponente">Escolha um Pokémon para enviar</label>
                    <select id="selecaoTrocaOponente"></select>
                    <div class="battle-tools">
                        <button id="confirmarTrocaOponente" type="button">Trocar Pokémon</button>
                        <button id="manterPokemonOponente" class="btn-secondary" type="button">
                            Manter Pokémon atual
                        </button>
                    </div>
                </section>

                <section class="battle-panel moves-panel" aria-labelledby="moves-title">
                    <div class="moves-heading">
                        <h2 id="moves-title">Ataques disponíveis</h2>
                        <p>Área Inicial: golpes até Nv. 20; depois, golpes até o nível atual do Pokémon.</p>
                    </div>
                    <div id="ataquesDisponiveis" class="move-grid" aria-live="polite">
                        <p>Inicie uma batalha para carregar os ataques.</p>
                    </div>
                    <div class="battle-tools" aria-label="Ação da batalha">
                        <button id="abrirMochila" class="btn-secondary inventory-button" type="button" disabled
                                aria-expanded="false" aria-controls="painelInventario">
                            <img src="assets/InventoryBag.png" alt="" width="32" height="32">
                            Mochila
                        </button>
                        <button id="fugirBatalha" class="btn-secondary" type="button" disabled>Encerrar batalha</button>
                    </div>
                    <section id="painelInventario" class="inventory-panel" aria-labelledby="inventory-title" hidden>
                        <div class="moves-heading">
                            <h2 id="inventory-title">Mochila</h2>
                            <p>Usar um item consome seu turno.</p>
                        </div>
                        <label for="alvoItem">Usar em</label>
                        <select id="alvoItem"></select>
                        <div id="itensInventario" class="inventory-grid" aria-live="polite"></div>
                        <p class="inventory-shop-link"><a href="shop.php">Comprar itens no Poké Mart</a></p>
                    </section>
                </section>

                <section id="equipeBatalha" class="battle-panel switch-panel" aria-labelledby="switch-title" hidden>
                    <div class="moves-heading">
                        <h2 id="switch-title">Seu time</h2>
                        <p>Trocar de Pokémon consome seu turno.</p>
                    </div>
                    <div id="opcoesPokemonTime" class="switch-grid" aria-live="polite"></div>
                </section>
            </div>

            <aside class="battle-panel log-panel" aria-labelledby="log-title">
                <h2 id="log-title">Log da batalha</h2>
                <ol id="logsBatalha" class="battle-log" aria-live="polite">
                    <li>Os acontecimentos da batalha aparecerão aqui.</li>
                </ol>
            </aside>
        </div>
    </main>
    <script src="JavaScript/Battle.js?v=<?= hash_file('sha256', __DIR__ . '/JavaScript/Battle.js') ?>"></script>
</body>
</html>
