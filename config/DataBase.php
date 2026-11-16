<?php
declare(strict_types=1);

namespace config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexão única com o banco (padrão Singleton).
 * Uso: $pdo = Database::conectar();
 *
 * ATENCAO ao nome do arquivo: ele se chama DataBase.php (o seu nome), mas a
 * CLASSE e `Database`. O autoload PSR-4 casa o nome do ARQUIVO com o da
 * CLASSE, entao o arquivo precisa estar em config/ e a classe declarada
 * aqui. Se preferir, renomeie o arquivo para Database.php.
 */
class Database
{
    /** Nome do banco - tem que ser igual ao criado no phpMyAdmin. */
    public const BANCO = 'Bancojogo';

    private static ?PDO $instancia = null;

    private function __construct()
    {
        // Impede "new Database()".
    }

    public static function conectar(): PDO
    {
        if (self::$instancia instanceof PDO) {
            return self::$instancia;
        }

        $host    = getenv('DB_HOST') ?: '127.0.0.1';
        $banco   = getenv('DB_NAME') ?: self::BANCO;
        $usuario = getenv('DB_USER') ?: 'root';
        $senha   = getenv('DB_PASS') ?: '';
        $porta   = getenv('DB_PORT') ?: '3306';

        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

        try {
            self::$instancia = new PDO($dsn, $usuario, $senha, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Mensagem que diz exatamente o que corrigir, em vez de "sem conexão".
            throw new ConexaoException(
                sprintf(
                    'Nao foi possivel conectar ao banco "%s" em %s:%s. Erro do MySQL: %s',
                    $banco,
                    $host,
                    $porta,
                    $e->getMessage()
                ),
                previous: $e
            );
        }

        return self::$instancia;
    }
}

/** Exceção customizada (requisito do trabalho). */
class ConexaoException extends RuntimeException
{
}
