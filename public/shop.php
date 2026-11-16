<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use config\ConexaoException;
use config\Database;
use src\controller\ShopController;
use src\core\Auth;
use src\dao\LojaDAO;

Auth::exigirLogin('../login/login.php');

if (empty($_SESSION['csrf_loja'])) {
    $_SESSION['csrf_loja'] = bin2hex(random_bytes(32));
}

$mensagem = $_SESSION['mensagem_loja'] ?? null;
unset($_SESSION['mensagem_loja']);
$erro = $_SESSION['erro_loja'] ?? null;
unset($_SESSION['erro_loja']);
$itens = [];
$saldo = null;

try {
    $controller = new ShopController(new LojaDAO(Database::conectar()));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string) ($_POST['csrf_token'] ?? '');

        if (!hash_equals($_SESSION['csrf_loja'], $token)) {
            http_response_code(403);
            $erro = 'A solicitação expirou. Atualize a página e tente novamente.';
        } else {
            try {
                $compra = $controller->comprar(
                    Auth::idPerfil(),
                    (int) ($_POST['id_item'] ?? 0)
                );
                $_SESSION['mensagem_loja'] = sprintf(
                    '%s comprado por %s Pokédollars.',
                    $compra['nome'],
                    number_format($compra['preco'], 0, ',', '.')
                );
            } catch (DomainException $e) {
                $_SESSION['erro_loja'] = $e->getMessage();
            }

            header('Location: shop.php');
            exit;
        }
    }

    $dados = $controller->carregarLoja(Auth::idPerfil());
    $itens = $dados['itens'];
    $saldo = $dados['saldo'];
} catch (DomainException $e) {
    Auth::deslogar();
    header('Location: ../login/login.php');
    exit;
} catch (PDOException | ConexaoException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível acessar a loja agora. Tente novamente mais tarde.';
}

$nomeTreinador = htmlspecialchars(Auth::nome(), ENT_QUOTES, 'UTF-8');
$imagensItens = [
    'Potion' => 'potionNV1.png',
    'Super Potion' => 'SuperPotionNV2.png',
    'Hyper Potion' => 'HyperPotionNV3.png',
    'Max Potion' => 'MaxPotionNV4.png',
    'Full Restore' => 'FullRestorePotionNV5.png',
    'Antidote' => 'AntidotePotion.png',
    'Burn Heal' => 'BurnHealPotion.png',
    'Ice Heal' => 'IceHealPotion.png',
    'Awakening' => 'AwakeningPotion.png',
    'Paralyze Heal' => 'ParalyzeHealPotion.png',
    'Full Heal' => 'FullHealPotion.png',
    'Revive' => 'RevivePotion.png',
];
$inventario = array_values(array_filter(
    $itens,
    static fn (array $item): bool => (int) $item['quantidade'] > 0
));
$abaAtual = ($_GET['aba'] ?? 'loja') === 'inventario' ? 'inventario' : 'loja';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONSTROBOLSO | Poké Mart</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body class="jogo">
    <header class="topo loja-cabecalho">
        <div class="loja-cabecalho-linha">
            <h1>Poké Mart</h1>
            <nav class="loja-cabecalho-acoes" role="tablist" aria-label="Loja e inventário">
                <a class="loja-aba"
                   id="aba-loja"
                   href="shop.php?aba=loja"
                   role="tab"
                   aria-controls="painel-loja"
                   aria-selected="<?= $abaAtual === 'loja' ? 'true' : 'false' ?>">
                    Loja
                </a>
                <a class="loja-aba"
                   id="aba-inventario"
                   href="shop.php?aba=inventario"
                   role="tab"
                   aria-controls="painel-inventario"
                   aria-selected="<?= $abaAtual === 'inventario' ? 'true' : 'false' ?>">
                    Inventário
                </a>
                <a href="index.php">Voltar ao Menu Principal</a>
            </nav>
        </div>
        <p>Olá, <strong><?= $nomeTreinador ?></strong>! Itens comprados vão para o seu inventário.</p>
        <p class="loja-saldo">
            <span>Saldo disponível</span>
            <strong>
                <?= $saldo === null ? 'indisponível' : number_format($saldo, 0, ',', '.') . ' Pokédollars' ?>
            </strong>
        </p>
    </header>

    <main class="loja-conteudo">
        <section id="painel-loja"
                 class="loja-painel"
                 role="tabpanel"
                 aria-labelledby="aba-loja"
                 <?= $abaAtual === 'loja' ? '' : 'hidden' ?>>
            <?php if ($mensagem): ?>
                <p class="loja-mensagem loja-sucesso" role="status">
                    <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if ($erro): ?>
                <p class="loja-mensagem loja-erro" role="alert">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if ($itens): ?>
                <section class="loja-grade" aria-label="Itens disponíveis">
                    <?php foreach ($itens as $item): ?>
                        <?php $preco = (int) $item['preco']; ?>
                        <?php
                        $nomeItem = (string) $item['nome'];
                        $imagemItem = $imagensItens[$nomeItem] ?? null;
                        ?>
                        <article class="loja-produto">
                            <p class="loja-categoria">
                                <?= htmlspecialchars($item['categoria'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php if ($imagemItem !== null): ?>
                                <img class="loja-imagem"
                                     src="assets/itens/<?= htmlspecialchars($imagemItem, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($nomeItem, ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="loja-imagem-vazia" role="img"
                                     aria-label="Imagem indisponível para <?= htmlspecialchars($nomeItem, ENT_QUOTES, 'UTF-8') ?>">
                                    Imagem indisponível
                                </div>
                            <?php endif; ?>
                            <h2><?= htmlspecialchars($nomeItem, ENT_QUOTES, 'UTF-8') ?></h2>
                            <p><?= htmlspecialchars($item['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="loja-quantidade">
                                No inventário: <?= (int) $item['quantidade'] ?>
                            </p>
                            <p class="loja-preco">
                                <?= number_format($preco, 0, ',', '.') ?> Pokédollars
                            </p>
                            <form method="POST" action="shop.php">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($_SESSION['csrf_loja'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id_item" value="<?= (int) $item['id_item'] ?>">
                                <button class="btn" type="submit"
                                    <?= $saldo === null || $saldo < $preco ? 'disabled' : '' ?>>
                                    Comprar
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php elseif (!$erro): ?>
                <p class="loja-mensagem">A loja está sem itens no momento.</p>
            <?php endif; ?>
        </section>

        <section id="painel-inventario"
                 class="loja-inventario loja-painel"
                 role="tabpanel"
                 aria-labelledby="aba-inventario"
                 <?= $abaAtual === 'inventario' ? '' : 'hidden' ?>>
            <h2 id="titulo-inventario">Meu inventário</h2>

            <?php if ($saldo === null): ?>
                <p>Não foi possível carregar o inventário agora.</p>
            <?php elseif ($inventario): ?>
                <div class="loja-inventario-lista">
                    <?php foreach ($inventario as $item): ?>
                        <?php
                        $nomeItem = (string) $item['nome'];
                        $imagemItem = $imagensItens[$nomeItem] ?? null;
                        ?>
                        <article class="loja-inventario-item">
                            <?php if ($imagemItem !== null): ?>
                                <img src="assets/itens/<?= htmlspecialchars($imagemItem, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="" loading="lazy">
                            <?php endif; ?>
                            <div>
                                <h3><?= htmlspecialchars($nomeItem, ENT_QUOTES, 'UTF-8') ?></h3>
                                <p>Quantidade: <?= (int) $item['quantidade'] ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Seu inventário está vazio.</p>
            <?php endif; ?>
        </section>
    </main>
    <script src="JavaScript/shop.js"></script>
</body>
</html>
