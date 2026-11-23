<?php
declare(strict_types=1);

/**
 * Ponto de entrada comum: liga o autoload e a sessão.
 * Todo controller e toda view incluem este arquivo primeiro.
 * Fica na RAIZ do projeto (Avaliacao-Final-Terceiro-Ano/bootstrap.php).
 */

require_once __DIR__ . '/src/core/Autoload.php';

use src\core\Autoload;

Autoload::registrar([
    'config\\'       => __DIR__ . '/config/',
    'src\\core\\'    => __DIR__ . '/src/core/',
    'src\\dao\\'     => __DIR__ . '/src/dao/',
    'models\\'       => __DIR__ . '/models/',
    'src\\controller\\' => __DIR__ . '/src/controller/',
]);

src\core\Auth::iniciar();
