<?php
/**
 * POKENOAS - Meus perfis (troca de perfil).
 * Caminho do projeto: public/perfil.php
 *
 * Requisito "Controle de Usuário (troca de perfis)".
 */

require_once __DIR__ . '/../bootstrap.php';

use src\core\Auth;
use config\Database;

Auth::exigirLogin('../login/login.php');

$pdo = Database::conectar();

$stmt = $pdo->prepare(
    'SELECT id_perfil, nome_perfil, pokedollar
       FROM perfis
      WHERE id_usuario = :id_usuario
      ORDER BY id_perfil ASC'
);
$stmt->execute([':id_usuario' => Auth::idUsuario()]);
$perfis = $stmt->fetchAll();

$idAtivo = Auth::idPerfil();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POKENOAS | Meus Perfis</title>
    <link rel="stylesheet" href="CSS/style.css">
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

        <nav class="menu">
            <?php foreach ($perfis as $p): ?>
                <a class="card-menu <?= ((int) $p['id_perfil'] === $idAtivo) ? 'card-time' : 'card-perfil' ?>"
                   href="../src/controller/roteadorPerfil.php?acao=trocar&id_perfil=<?= (int) $p['id_perfil'] ?>">
                    <span class="card-titulo">
                        <?= htmlspecialchars($p['nome_perfil'], ENT_QUOTES, 'UTF-8') ?>
                        <?= ((int) $p['id_perfil'] === $idAtivo) ? ' (ativo)' : '' ?>
                    </span>
                    <span class="card-desc">
                        <?= number_format((int) $p['pokedollar'], 0, ',', '.') ?> pokédollars
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    </main>
</body>
</html>
