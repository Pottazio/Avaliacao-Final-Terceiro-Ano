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
                'rota' => '../../public/index.php',
            ]);
        } catch (CredenciaisInvalidasException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (DadosInvalidosException $e) {
            $this->responder(false, $e->getMessage(), error: true, status: 400);
        } catch (PDOException $e) {
            error_log('[LoginController::entrar] ' . $e->getMessage());
            $this->responder(false, 'Erro ao consultar o banco de dados.', error: true, status: 500);
        }
    }

    /** POST nome + email + senha -> cria usuário e perfil sem abrir sessão */
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
            $this->responder(false, $e->getMessage(), error: true, status: 400);
        } catch (PDOException $e) {
            // 1062 = e-mail duplicado (constraint UQ_usuarios_email).
            $duplicado = (int) ($e->errorInfo[1] ?? 0) === 1062;
            if (!$duplicado) {
                error_log('[LoginController::cadastrar] ' . $e->getMessage());
            }
            $msg = $duplicado ? 'Este e-mail já está cadastrado.' : 'Erro ao cadastrar. Tente novamente.';
            $this->responder(false, $msg, error: true, status: $duplicado ? 409 : 500);
        }
    }

    /** POST id_perfil -> troca o perfil ativo da sessão */
    public function trocarPerfil(): void
    {
        $idPerfil = (int) ($_POST['id_perfil'] ?? 0);

        foreach ($this->usuarioModel->listarPerfis() as $p) {
            if ((int) $p['id_perfil'] === $idPerfil) {
                Auth::trocarPerfil($idPerfil);
                $this->responder(true, 'Perfil trocado.', ['rota' => '../../public/index.php']);
                return;
            }
        }

        $this->responder(false, 'Perfil inválido.', error: true, status: 400);
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
    private function responder(
        bool $sucesso,
        string $mensagem,
        array $extra = [],
        bool $error = false,
        ?int $status = null
    ): void
    {
        if ($this->ehAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($status ?? ($error ? 401 : 200));
            echo json_encode(
                ['sucesso' => $sucesso, 'mensagem' => $mensagem] + $extra,
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        // Form tradicional (sem JS): mensagem pela sessão e volta.
        if (!$sucesso) {
            $_SESSION['erro_login'] = $mensagem;
            $destino = ($_GET['acao'] ?? $_POST['acao'] ?? '') === 'cadastrar'
                ? '../../login/cadastro.php'
                : '../../login/login.php';
            header('Location: ' . $destino);
            exit;
        }

        header('Location: ' . ($extra['rota'] ?? '../../public/index.php'));
        exit;
    }

    private function rotaPublica(string $caminho): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $raizProjeto = dirname($script, 3);
        $prefixo = $raizProjeto === '/' || $raizProjeto === '.'
            ? ''
            : rtrim($raizProjeto, '/');

        return $prefixo . $caminho;
    }
}
