<?php
declare(strict_types=1);

namespace src\core;

/**
 * Guarda de sessão. Inclua o bootstrap e chame nas páginas protegidas:
 *
 *     require __DIR__ . '/../bootstrap.php';
 *     Auth::exigirLogin('../login/login.php');
 */
class Auth
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function logar(int $idUsuario, int $idPerfil, string $nome): void
    {
        self::iniciar();
        session_regenerate_id(true); // evita fixação de sessão

        $_SESSION['id_usuario'] = $idUsuario;
        $_SESSION['id_perfil']  = $idPerfil;
        $_SESSION['nome']       = $nome;
    }

    public static function deslogar(): void
    {
        self::iniciar();
        $_SESSION = [];
        session_destroy();
    }

    public static function logado(): bool
    {
        self::iniciar();
        return !empty($_SESSION['id_perfil']);
    }

    /** Redireciona para o login e encerra, se não houver sessão válida. */
    public static function exigirLogin(string $destino): void
    {
        if (!self::logado()) {
            header('Location: ' . $destino);
            exit;
        }
    }

    /** id_perfil é o que times, inventarios e compras usam. */
    public static function idPerfil(): int
    {
        self::iniciar();
        return (int) ($_SESSION['id_perfil'] ?? 0);
    }

    public static function idUsuario(): int
    {
        self::iniciar();
        return (int) ($_SESSION['id_usuario'] ?? 0);
    }

    public static function nome(): string
    {
        self::iniciar();
        return (string) ($_SESSION['nome'] ?? '');
    }

    /** Troca de perfil (requisito "Controle de Usuário"). */
    public static function trocarPerfil(int $idPerfil): void
    {
        self::iniciar();
        $_SESSION['id_perfil'] = $idPerfil;
    }
}
