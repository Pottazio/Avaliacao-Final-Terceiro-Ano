<?php
declare(strict_types=1);

/**
 * POKENOAS - Roteador da troca de perfil.
 * Caminho do projeto: src/controller/roteadorPerfil.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use src\core\Auth;
use config\Database;

Auth::exigirLogin('../../login/login.php');

$acao = $_GET['acao'] ?? 'trocar';

if ($acao === 'trocar') {
    $idPerfil = (int) ($_GET['id_perfil'] ?? 0);

    // Só troca para um perfil que seja DESTE usuário.
    $stmt = Database::conectar()->prepare(
        'SELECT id_perfil FROM perfis
          WHERE id_perfil = :id_perfil AND id_usuario = :id_usuario'
    );
    $stmt->execute([
        ':id_perfil'  => $idPerfil,
        ':id_usuario' => Auth::idUsuario(),
    ]);

    if ($stmt->fetch() !== false) {
        Auth::trocarPerfil($idPerfil);
    }
}

header('Location: ../../public/perfil.php');
exit;
