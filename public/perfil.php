<?php
/**
 * POKENOAS - Meus perfis (troca de perfil).
 * Caminho do projeto: public/perfil.php
 *
 * Requisito "Controle de Usuário (troca de perfis)".
 */

require __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;

Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();

$stmt = $pdo->prepare(
    'SELECT id_perfil, nome_perfil, genero, pokedollar
       FROM vw_perfis_usuario
      WHERE id_usuario = :id_usuario
      ORDER BY id_perfil ASC'
);
$stmt->execute([':id_usuario' => Auth::idUsuario()]);
$perfis = $stmt->fetchAll();

$idAtivo = Auth::idPerfil();
$csrfToken = $_SESSION['csrf_perfil'] ?? '';
if (!is_string($csrfToken) || $csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
}
$_SESSION['csrf_perfil'] = $csrfToken;
$statusSolicitado = $_GET['status'] ?? '';
$status = is_string($statusSolicitado) ? $statusSolicitado : '';
$mensagens = [
    'criado' => 'Novo perfil criado e ativado.',
    'nome' => 'Informe um nome de perfil válido (até 50 caracteres).',
    'atualizado' => 'Perfil atualizado.',
    'dados_perfil' => 'Confira o nome e o gênero selecionados.',
    'csrf' => 'A sessão expirou. Atualize a página e tente novamente.',
    'erro' => 'Não foi possível salvar o perfil.',
    'removido' => 'Perfil removido.',
    'ultimo_perfil' => 'Não é possível remover o único perfil da conta.',
    'perfil_em_batalha' => 'Encerre a batalha deste perfil antes de removê-lo.',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Meus Perfis</title>
    <link rel="stylesheet" href="CSS/style.css?v=<?= (int) filemtime(__DIR__ . '/CSS/style.css') ?>">
</head>
<body class="jogo">
    <main class="painel">
        <header class="topo">
            <div class="topo-marca">
                <h1>POKENOAS</h1>
            </div>
            <div class="topo-treinador">
                <div class="topo-dados">
                    <p class="topo-nome">Meus perfis</p>
                    <p class="topo-perfil">Escolha com qual treinador jogar</p>
                </div>
            </div>
            <a class="btn-sair" href="index.php">Voltar</a>
        </header>

        <?php if (isset($mensagens[$status])): ?>
            <p class="login-msg" data-tipo="<?= in_array($status, ['criado', 'atualizado', 'removido'], true) ? 'ok' : 'info' ?>" role="status">
                <?= htmlspecialchars($mensagens[$status], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <section class="perfil-grid" aria-label="Perfis da conta">
            <?php foreach ($perfis as $p): ?>
                <article class="perfil-gerenciamento">
                    <div class="perfil-gerenciamento-cabecalho">
                        <div>
                            <h2><?= htmlspecialchars($p['nome_perfil'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p><?= number_format((int) $p['pokedollar'], 0, ',', '.') ?> pokédollars</p>
                        </div>
                        <a class="perfil-ativar <?= ((int) $p['id_perfil'] === $idAtivo) ? 'perfil-ativo' : '' ?>"
                           href="../src/controller/roteadorPerfil.php?acao=trocar&id_perfil=<?= (int) $p['id_perfil'] ?>">
                            <?= ((int) $p['id_perfil'] === $idAtivo) ? 'Perfil ativo' : 'Usar perfil' ?>
                        </a>
                    </div>
                    <form class="perfil-editar-form" action="../src/controller/roteadorPerfil.php" method="post">
                        <input type="hidden" name="acao" value="atualizar">
                        <input type="hidden" name="id_perfil" value="<?= (int) $p['id_perfil'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="nomePerfil<?= (int) $p['id_perfil'] ?>">Nome do perfil</label>
                        <input id="nomePerfil<?= (int) $p['id_perfil'] ?>" name="nome_perfil" type="text"
                               maxlength="50" value="<?= htmlspecialchars($p['nome_perfil'], ENT_QUOTES, 'UTF-8') ?>" required>
                        <label for="generoPerfil<?= (int) $p['id_perfil'] ?>">Personagem</label>
                        <select id="generoPerfil<?= (int) $p['id_perfil'] ?>" name="genero">
                            <option value="masculino" <?= $p['genero'] === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="feminino" <?= $p['genero'] === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                        </select>
                        <button type="submit">Salvar alterações</button>
                    </form>
                    <form class="perfil-remover-form" action="../src/controller/roteadorPerfil.php" method="post"
                          onsubmit="return confirm('Remover este perfil e todos os seus dados? Esta ação não pode ser desfeita.')">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="id_perfil" value="<?= (int) $p['id_perfil'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="perfil-remover" type="submit">Remover perfil</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
        <section class="login-card">
            <h2>Criar outro perfil</h2>
            <p>Cada perfil mantém seu próprio time, inventário e saldo.</p>
            <form class="login-form" action="../src/controller/roteadorPerfil.php" method="post">
                <input type="hidden" name="acao" value="criar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="nomePerfil">Nome do perfil</label>
                <input id="nomePerfil" name="nome_perfil" type="text" maxlength="50" required>
                <button type="submit">Criar e ativar perfil</button>
            </form>
        </section>
    </main>
</body>
</html>
