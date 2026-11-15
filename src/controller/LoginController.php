<?php
declare(strict_types=1);

namespace src\controller;

use PDOException;
use config\Database;
use models\Usuario;
use models\CredenciaisInvalidasException;
use models\DadosInvalidosException;
use src\core\Auth;

/**
 * Controller de login e cadastro.
 * Responde JSON quando a requisição é assíncrona (fetch do Login.js)
 * e redireciona quando vem de um <form> tradicional.
 */
class LoginController
{
    private Usuario $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new Usuario(Database::conectar());
    }

    /** POST email + senha -> abre a sessão */
    public function entrar(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');

        try {
            if ($email === '' || $senha === '') {
                throw new DadosInvalidosException('Informe e-mail e senha.');
            }

            $dados = $this->usuarioModel->autenticar($email, $senha);

            Auth::logar($dados['id_usuario'], $dados['id_perfil'], $dados['nome']);

            $this->responder(true, 'Login realizado com sucesso.', [
                'nome' => $dados['nome'],
                'rota' => '../public/index.php',
            ]);
        } catch (CredenciaisInvalidasException | DadosInvalidosException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao consultar o banco de dados.', error: true);
        }
    }

    /** POST nome + email + senha -> cria usuário e perfil */
    public function cadastrar(): void
    {
        $nome  = (string) ($_POST['nome'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');

        try {
            $this->usuarioModel
                ->setNome($nome)
                ->setEmail($email)
                ->setSenha($senha);

            $idPerfil = $this->usuarioModel->cadastrar();

            Auth::logar(
                (int) $this->usuarioModel->getIdUsuario(),
                $idPerfil,
                $this->usuarioModel->getNome()
            );

            $this->responder(true, 'Conta criada com sucesso.', [
                'rota' => '../public/index.php',
            ]);
        } catch (DadosInvalidosException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (PDOException $e) {
            // 1062 = e-mail duplicado (constraint UQ_usuarios_email).
            $msg = ((int) ($e->errorInfo[1] ?? 0) === 1062)
                ? 'Este e-mail já está cadastrado.'
                : 'Erro ao cadastrar. Tente novamente.';
            $this->responder(false, $msg, error: true);
        }
    }

    /** POST id_perfil -> troca o perfil ativo da sessão */
    public function trocarPerfil(): void
    {
        $idPerfil = (int) ($_POST['id_perfil'] ?? 0);

        foreach ($this->usuarioModel->listarPerfis() as $p) {
            if ((int) $p['id_perfil'] === $idPerfil) {
                Auth::trocarPerfil($idPerfil);
                $this->responder(true, 'Perfil trocado.', ['rota' => '../public/index.php']);
                return;
            }
        }

        $this->responder(false, 'Perfil inválido.', error: true);
    }

    public function sair(): void
    {
        Auth::deslogar();
        header('Location: ../../login/login.php');
        exit;
    }

    // ---------- helpers ----------

    private function ehAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /** Transporte de dados via JSON (requisito do trabalho). */
    private function responder(bool $sucesso, string $mensagem, array $extra = [], bool $error = false): void
    {
        if ($this->ehAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($error ? 401 : 200);
            echo json_encode(
                ['sucesso' => $sucesso, 'mensagem' => $mensagem] + $extra,
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        // Form tradicional (sem JS): mensagem pela sessão e volta.
        if (!$sucesso) {
            $_SESSION['erro_login'] = $mensagem;
            header('Location: ../../login/login.php');
            exit;
        }

        header('Location: ' . ($extra['rota'] ?? '../public/index.php'));
        exit;
    }
}
