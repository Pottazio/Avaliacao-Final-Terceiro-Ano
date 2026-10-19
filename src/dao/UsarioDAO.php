
<?php

require_once __DIR__ . "/../../config/Database.php";

class UsuarioDAO
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::conectar();
    }

    public function buscarPorEmail(string $email): array|false
    {
        $sql = "SELECT id_usuario, nome, email, senha
                FROM usuarios
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["email" => $email]);

        return $stmt->fetch();
    }
}
