<?php
declare(strict_types=1);

namespace src\core;

/**
 * Autoload PSR-4 simples, sem Composer.
 * Registrado no bootstrap.php.
 */
class Autoload
{
    /** @var array<string,string> */
    private static array $raizes = [];

    public static function registrar(array $raizes): void
    {
        self::$raizes = $raizes;

        spl_autoload_register(static function (string $classe): void {
            foreach (self::$raizes as $prefixo => $diretorio) {
                if (str_starts_with($classe, $prefixo)) {
                    $relativo = substr($classe, strlen($prefixo));
                    $arquivo  = $diretorio . str_replace('\\', '/', $relativo) . '.php';

                    if (is_file($arquivo)) {
                        require $arquivo;
                        return;
                    }
                }
            }
        });
    }
}
