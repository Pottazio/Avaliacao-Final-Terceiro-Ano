<?php
declare(strict_types=1);

/**
 * POKENOAS - Roteador da troca de perfil.
 * Caminho do projeto: src/controller/roteadorPerfil.php
 */

require __DIR__ . '/../../bootstrap.php';

use models\DadosInvalidosException;
use models\Usuario;
use src\core\Auth;
use config\Database;
use config\ConexaoException;

Auth::exigirLogin('../../login/login.php');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo === 'POST') {
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSessao = $_SESSION['csrf_perfil'] ?? '';
    $csrfRecebido = is_string($csrfPost) ? $csrfPost : '';
    $csrfEsperado = is_string($csrfSessao) ? $csrfSessao : '';
    if ($csrfRecebido === '' || $csrfEsperado === '' || !hash_equals($csrfEsperado, $csrfRecebido)) {
        header('Location: ../../public/perfil.php?status=csrf');
        exit;
    }

    if (($_POST['acao'] ?? '') === 'criar') {
        $nomePerfil = $_POST['nome_perfil'] ?? '';
        try {
            $usuario = new Usuario(Database::conectar());
            $idPerfil = $usuario->criarPerfil(
                Auth::idUsuario(),
                is_string($nomePerfil) ? $nomePerfil : ''
            );
            Auth::trocarPerfil($idPerfil);
            header('Location: ../../public/perfil.php?status=criado');
            exit;
        } catch (DadosInvalidosException $erro) {
            header('Location: ../../public/perfil.php?status=nome');
            exit;
        } catch (\PDOException | ConexaoException $erro) {
            error_log($erro->getMessage());
            header('Location: ../../public/perfil.php?status=erro');
            exit;
        }
    }

    if (($_POST['acao'] ?? '') === 'atualizar') {
        $idRecebido = filter_var($_POST['id_perfil'] ?? null, FILTER_VALIDATE_INT);
        $nomePerfil = $_POST['nome_perfil'] ?? '';
        $genero = $_POST['genero'] ?? '';
        try {
            if ($idRecebido === false || $idRecebido === null) {
                throw new DadosInvalidosException('Perfil inválido.');
            }
            $usuario = new Usuario(Database::conectar());
            $usuario->atualizarPerfil(
                Auth::idUsuario(),
                (int) $idRecebido,
                is_string($nomePerfil) ? $nomePerfil : '',
                is_string($genero) ? $genero : ''
            );
            header('Location: ../../public/perfil.php?status=atualizado');
            exit;
        } catch (DadosInvalidosException $erro) {
            header('Location: ../../public/perfil.php?status=dados_perfil');
            exit;
        } catch (\PDOException | ConexaoException $erro) {
            error_log($erro->getMessage());
            header('Location: ../../public/perfil.php?status=erro');
            exit;
        }
    }

    http_response_code(400);
    exit;
}

if ($metodo !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$acaoSolicitada = $_GET['acao'] ?? 'trocar';
$acao = is_string($acaoSolicitada) ? $acaoSolicitada : '';

if ($acao === 'trocar') {
    $idRecebido = filter_var($_GET['id_perfil'] ?? null, FILTER_VALIDATE_INT);
    $idPerfil = $idRecebido === false ? 0 : (int) $idRecebido;

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
