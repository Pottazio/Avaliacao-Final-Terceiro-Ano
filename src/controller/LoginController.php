```php
<?php

session_start();

require_once __DIR__ . "/../dao/UsarioDAO.php";

class LoginController
{
    private $usuarioDAO;

    public function __construct()
    {
        $this->usuarioDAO = new UsarioDAO();
    }

    public function autenticar()
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("Location: ../../login/login.php");
            exit;
        }

        $email = trim($_POST["email"] ?? "");
        $senha = $_POST["senha"] ?? "";

        if ($email === "" || $senha === "") {

            $_SESSION["erro_login"] = "Preencha todos os campos.";

            header("Location: ../../login/login.php");
            exit;
        }

        try {

            $usuario = $this->usuarioDAO->buscarPorEmail($email);

            if (!$usuario) {

                $_SESSION["erro_login"] =
                    "E-mail ou senha incorretos.";

                header("Location: ../../login/login.php");
                exit;
            }

            if (!password_verify($senha, $usuario["senha"])) {

                $_SESSION["erro_login"] =
                    "E-mail ou senha incorretos.";

                header("Location: ../../login/login.php");
                exit;
            }

            session_regenerate_id(true);

            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_email"] = $usuario["email"];

            if (isset($usuario["nome"])) {
                $_SESSION["usuario_nome"] = $usuario["nome"];
            }

            header("Location: ../../public/index.php");
            exit;

        } catch (PDOException $e) {

            $_SESSION["erro_login"] =
                "Erro ao acessar o banco de dados.";

            header("Location: ../../login/login.php");
            exit;
        }
    }
}


$controller = new LoginController();

$controller->autenticar();
```