<?php
declare(strict_types=1);

namespace models;

use PDO;
use Throwable;
use RuntimeException;
use src\core\Auth;

/**
 * Usuario 1 --- N Perfil  (agregação: um usuário tem vários perfis).
 *
 * Ao cadastrar, criamos o usuário e o perfil inicial na mesma transação
 * (DTL), porque times/inventarios/compras apontam para id_perfil.
 */
class Usuario
{
    private ?int $idUsuario = null;
    private string $nome = '';
    private string $email = '';
    private string $senhaHash = '';

    public function __construct(private PDO $conexao)
    {
    }

    // ---------- getters / setters (encapsulamento) ----------

    public function getIdUsuario(): ?int
    {
        return $this->idUsuario;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSenhaHash(): string
    {
        return $this->senhaHash;
    }

    public function setIdUsuario(int $id): self
    {
        $this->idUsuario = $id;
        return $this;
    }

    public function setNome(string $nome): self
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new DadosInvalidosException('O nome é obrigatório.');
        }
        if (mb_strlen($nome) > 100) {
            throw new DadosInvalidosException('O nome deve ter no máximo 100 caracteres.');
        }
        $this->nome = $nome;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DadosInvalidosException('E-mail inválido.');
        }
        if (mb_strlen($email) > 150) {
            throw new DadosInvalidosException('O e-mail deve ter no máximo 150 caracteres.');
        }
        $this->email = $email;
        return $this;
    }

    /** Recebe a senha CRUA e guarda apenas o hash bcrypt. */
    public function setSenha(string $senha): self
    {
        if (strlen($senha) < 6) {
            throw new DadosInvalidosException('A senha precisa ter ao menos 6 caracteres.');
        }
        $this->senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        return $this;
    }

    // ---------- persistência ----------

    /**
     * Cadastra o usuário e cria o perfil inicial.
     * Retorna o id do perfil criado.
     */
    public function cadastrar(string $nomePerfil = 'Treinador'): int
    {
        try {
            $this->conexao->beginTransaction(); // DTL

            $sql = 'INSERT INTO usuarios (nome, email, senha)
                    VALUES (:nome, :email, :senha)';
            $stmt = $this->conexao->prepare($sql);
            $stmt->execute([
                ':nome'  => $this->nome,
                ':email' => $this->email,
                ':senha' => $this->senhaHash,
            ]);

            $this->idUsuario = (int) $this->conexao->lastInsertId();

            $sqlPerfil = 'INSERT INTO perfis (id_usuario, nome_perfil, pokedollar)
                          VALUES (:id_usuario, :nome_perfil, 1000)';
            $stmtPerfil = $this->conexao->prepare($sqlPerfil);
            $stmtPerfil->execute([
                ':id_usuario'  => $this->idUsuario,
                ':nome_perfil' => $nomePerfil,
            ]);

            $idPerfil = (int) $this->conexao->lastInsertId();

            $this->conexao->commit();

            return $idPerfil;
        } catch (RuntimeException $e) {
            if ($this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }
            throw $e;
        }
    }

    /** Busca pelo e-mail. Retorna null se não existir. */
    public function buscarPorEmail(string $email): ?array
    {
        $sql  = 'SELECT id_usuario, nome, email, senha FROM usuarios WHERE email = :email LIMIT 1';
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':email' => $email]);

        $linha = $stmt->fetch();
        return $linha === false ? null : $linha;
    }

    /**
     * Autentica e devolve os dados de sessão.
     * A senha NÃO entra no WHERE: o hash muda a cada gravação (salt),
     * por isso a comparação é feita com password_verify().
     */
    public function autenticar(string $email, string $senha): array
    {
        $registro = $this->buscarPorEmail(trim($email));

        // Mensagem genérica de propósito: não revela qual dos dois errou.
        if ($registro === null) {
            throw new CredenciaisInvalidasException('E-mail ou senha incorretos.');
        }

        if (!password_verify($senha, $registro['senha'])) {
            throw new CredenciaisInvalidasException('E-mail ou senha incorretos.');
        }

        $perfil = $this->perfilPadrao((int) $registro['id_usuario']);
        if ($perfil === null) {
            throw new RuntimeException('Usuário sem perfil associado.');
        }

        return [
            'id_usuario' => (int) $registro['id_usuario'],
            'id_perfil'  => (int) $perfil['id_perfil'],
            'nome'       => (string) $registro['nome'],
        ];
    }

    /** Primeiro perfil do usuário (usado no login). */
    public function perfilPadrao(int $idUsuario): ?array
    {
        $sql  = 'SELECT id_perfil, nome_perfil, pokedollar
                 FROM perfis WHERE id_usuario = :id_usuario
                 ORDER BY id_perfil ASC';
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id_usuario' => $idUsuario]);

        $linha = $stmt->fetch();
        return $linha === false ? null : $linha;
    }

    /** Lista os perfis do usuário logado (troca de perfil). */
    public function listarPerfis(): array
    {
        $sql  = 'SELECT id_perfil, nome_perfil, pokedollar
                 FROM perfis WHERE id_usuario = :id_usuario
                 ORDER BY id_perfil ASC';
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id_usuario' => Auth::idUsuario()]);

        return $stmt->fetchAll();
    }

    public function criarPerfil(int $idUsuario, string $nomePerfil): int
    {
        $nomePerfil = trim($nomePerfil);
        if ($idUsuario < 1 || $nomePerfil === '' || mb_strlen($nomePerfil) > 50) {
            throw new DadosInvalidosException('Informe um nome de perfil válido com até 50 caracteres.');
        }

        $transacaoPropria = !$this->conexao->inTransaction();
        if ($transacaoPropria) {
            $this->conexao->beginTransaction();
        }

        try {
            $stmt = $this->conexao->prepare(
                'INSERT INTO perfis (id_usuario, nome_perfil, pokedollar)
                 VALUES (:id_usuario, :nome_perfil, 1000)'
            );
            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':nome_perfil' => $nomePerfil,
            ]);
            $idPerfil = (int) $this->conexao->lastInsertId();

            if ($transacaoPropria) {
                $this->conexao->commit();
            }
            return $idPerfil;
        } catch (Throwable $erro) {
            if ($transacaoPropria && $this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }
            throw $erro;
        }
    }

    public function atualizarPerfil(
        int $idUsuario,
        int $idPerfil,
        string $nomePerfil,
        string $genero
    ): void {
        $nomePerfil = trim($nomePerfil);
        if (
            $idUsuario < 1
            || $idPerfil < 1
            || $nomePerfil === ''
            || mb_strlen($nomePerfil) > 50
            || !in_array($genero, ['masculino', 'feminino'], true)
        ) {
            throw new DadosInvalidosException('Informe um nome e um gênero válidos para o perfil.');
        }

        $stmt = $this->conexao->prepare(
            'UPDATE perfis
                SET nome_perfil = :nome_perfil,
                    genero = :genero
              WHERE id_perfil = :id_perfil
                AND id_usuario = :id_usuario'
        );
        $stmt->execute([
            ':nome_perfil' => $nomePerfil,
            ':genero' => $genero,
            ':id_perfil' => $idPerfil,
            ':id_usuario' => $idUsuario,
        ]);
        if ($stmt->rowCount() > 0) {
            return;
        }

        $stmt = $this->conexao->prepare(
            'SELECT 1 FROM perfis
              WHERE id_perfil = :id_perfil AND id_usuario = :id_usuario'
        );
        $stmt->execute([
            ':id_perfil' => $idPerfil,
            ':id_usuario' => $idUsuario,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new DadosInvalidosException('Esse perfil não pertence à sua conta.');
        }
    }

    public function removerPerfil(int $idUsuario, int $idPerfil): void
    {
        if ($idUsuario < 1 || $idPerfil < 1) {
            throw new DadosInvalidosException('Perfil inválido.');
        }

        $this->conexao->beginTransaction();
        try {
            $stmt = $this->conexao->prepare(
                'SELECT COUNT(*) FROM perfis WHERE id_usuario = :id_usuario'
            );
            $stmt->execute([':id_usuario' => $idUsuario]);
            $quantidade = (int) $stmt->fetchColumn();
            if ($quantidade <= 1) {
                throw new DadosInvalidosException('Não é possível remover o único perfil da conta.');
            }

            $stmt = $this->conexao->prepare(
                'SELECT 1 FROM perfis WHERE id_perfil = :id_perfil AND id_usuario = :id_usuario'
            );
            $stmt->execute([':id_perfil' => $idPerfil, ':id_usuario' => $idUsuario]);
            if ($stmt->fetchColumn() === false) {
                throw new DadosInvalidosException('Esse perfil não pertence à sua conta.');
            }

            $stmt = $this->conexao->prepare("SELECT COUNT(*) FROM batalhas WHERE id_perfil = :id_perfil AND resultado = 'EM_ANDAMENTO'");
            $stmt->execute([':id_perfil' => $idPerfil]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new DadosInvalidosException('Encerre a batalha deste perfil antes de removê-lo.');
            }

            $stmt = $this->conexao->prepare('SELECT id_time FROM times WHERE id_perfil = :id_perfil');
            $stmt->execute([':id_perfil' => $idPerfil]);
            $idsTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($idsTimes as $idTime) {
                $deleteMovimentos = $this->conexao->prepare(
                    'DELETE tm FROM time_pokemon_movimentos tm JOIN time_pokemons tp ON tp.id_time = tm.id_time AND tp.id_pokemon = tm.id_pokemon WHERE tp.id_time = :id_time'
                );
                $deleteMovimentos->execute([':id_time' => (int) $idTime]);

                $deleteTimePokemons = $this->conexao->prepare('DELETE FROM time_pokemons WHERE id_time = :id_time');
                $deleteTimePokemons->execute([':id_time' => (int) $idTime]);
            }

            $tabelas = [
                'DELETE FROM batalha_logs WHERE id_batalha IN (SELECT id_batalha FROM batalhas WHERE id_perfil = :id_perfil)',
                'DELETE FROM batalha_pokemons WHERE id_batalha IN (SELECT id_batalha FROM batalhas WHERE id_perfil = :id_perfil)',
                'DELETE FROM compras WHERE id_perfil = :id_perfil',
                'DELETE FROM inventarios WHERE id_perfil = :id_perfil',
                'DELETE FROM progresso_jogo WHERE id_perfil = :id_perfil',
                'DELETE FROM batalhas WHERE id_perfil = :id_perfil',
                'DELETE FROM times WHERE id_perfil = :id_perfil',
                'DELETE FROM perfis WHERE id_perfil = :id_perfil AND id_usuario = :id_usuario',
            ];
            foreach ($tabelas as $sql) {
                $delete = $this->conexao->prepare($sql);
                $delete->execute(str_contains($sql, 'AND id_usuario')
                    ? [':id_perfil' => $idPerfil, ':id_usuario' => $idUsuario]
                    : [':id_perfil' => $idPerfil]);
            }

            $this->conexao->commit();
        } catch (Throwable $erro) {
            if ($this->conexao->inTransaction()) {
                $this->conexao->rollBack();
            }
            throw $erro;
        }
    }
}

/** Exceção customizada (1 das 3 exigidas). */
class CredenciaisInvalidasException extends RuntimeException
{
}

/** Segunda exceção customizada. */
class DadosInvalidosException extends RuntimeException
{
}
