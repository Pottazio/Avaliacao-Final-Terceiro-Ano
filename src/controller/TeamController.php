<?php
declare(strict_types=1);

namespace src\controller;

use PDOException;
use RuntimeException;
use config\Database;
use models\Pokemon;
use models\Time;
use models\ProgressaoJogo;
use models\DadosPokemonInvalidosException;
use models\TimeInvalidoException;
use src\core\Auth;

/**
 * POKENOAS - Controller do Team Builder.
 *
 * Consumo de API: os atributos do Pokemon vem da PokeAPI no lado do
 * cliente (JavaScript) e chegam aqui ja normalizados no formato da
 * tabela `pokedex`. Assim o servidor nao depende de rede externa para
 * gravar, e a resposta sai sempre em JSON.
 */
class TeamController
{
    private Pokemon $pokemonModel;
    private Time $timeModel;

    public function __construct()
    {
        $pdo = Database::conectar();
        $this->pokemonModel = new Pokemon($pdo);
        $this->timeModel    = new Time($pdo);
    }

    /**
     * GET/POST ?acao=buscar&nome=pikachu
     * Procura o Pokemon no banco local (ja adicionado antes).
     */
    public function buscar(): void
    {
        $nome = trim((string) ($_GET['nome'] ?? $_POST['nome'] ?? ''));

        if ($nome === '') {
            $this->responder(false, 'Informe o nome do Pokémon.', error: true);
            return;
        }

        try {
            $encontrado = $this->pokemonModel->buscarPorNome($nome);

            if ($encontrado === null) {
                $this->responder(true, 'Pokémon ainda não cadastrado no jogo.', [
                    'registrado' => false,
                    'pokemon'    => null,
                ]);
                return;
            }

            $this->responder(true, 'Pokémon encontrado.', [
                'registrado' => true,
                'pokemon'    => $encontrado,
            ]);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao consultar o Pokémon.', error: true);
        }
    }

    /**
     * POST ?acao=adicionar
     * Campos: nome, id_pokedex, especie, HP, ATQ, ATQE, DEF, DEFE, SPE, nivel, tipos[]
     * Grava na pokedex, cria o Pokemon, associa os tipos e insere no time.
     */
    public function adicionar(): void
    {
        $idPerfil = Auth::idPerfil();

        $nome      = trim((string) ($_POST['nome'] ?? ''));
        $idPokedex = (int) ($_POST['id_pokedex'] ?? 0);
        $especie   = trim((string) ($_POST['especie'] ?? $nome));
        $nivel     = filter_var($_POST['nivel'] ?? 1, FILTER_VALIDATE_INT);

        if ($idPokedex < 1 || $idPokedex > Pokemon::MAX_PRIMEIRA_GERACAO) {
            $this->responder(false, 'Só é possível escolher Pokémon da primeira geração (#001–#151).', error: true);
            return;
        }

        // tipos[] pode chegar como array ou como lista separada por virgula.
        $tipos = $_POST['tipos'] ?? [];
        if (is_string($tipos)) {
            $tipos = array_filter(array_map('trim', explode(',', $tipos)));
        }

        try {
            $progresso = ProgressaoJogo::carregar(Database::conectar(), $idPerfil);
            if ($nivel === false || $nivel < 1 || $nivel > $progresso['nivel_maximo']) {
                $this->responder(
                    false,
                    "Escolha um nível entre 1 e {$progresso['nivel_maximo']}.",
                    error: true
                );
                return;
            }

            // 1) Grava os atributos na pokedex (fonte: PokeAPI)
            $this->pokemonModel->salvarNaPokedex($idPokedex, $especie, [
                'HP'   => (int) ($_POST['HP'] ?? 0),
                'ATQ'  => (int) ($_POST['ATQ'] ?? 0),
                'ATQE' => (int) ($_POST['ATQE'] ?? 0),
                'DEF'  => (int) ($_POST['DEF'] ?? 0),
                'DEFE' => (int) ($_POST['DEFE'] ?? 0),
                'SPE'  => (int) ($_POST['SPE'] ?? 0),
            ]);

            // 2) O Pokemon ja existe no jogo? Reaproveita em vez de duplicar.
            $existente = $this->pokemonModel->buscarPorNome($nome);

            if ($existente !== null) {
                $idPokemon = (int) $existente['id_pokemon'];
            } else {
                $this->pokemonModel
                    ->setIdPokedex($idPokedex)
                    ->setNome($nome)
                    ->setStatus('NORMAL');

                $this->pokemonModel->setTipos(array_values((array) $tipos));

                $idPokemon = $this->pokemonModel->salvar();
            }

            // 3) Carrega o time do perfil e insere (AGREGACAO)
            $this->timeModel->doPerfil($idPerfil);
            $posicao = $this->timeModel->inserirPokemon($idPokemon, $nivel);

            $this->responder(true, "{$nome} entrou no time!", [
                'id_pokemon' => $idPokemon,
                'posicao'    => $posicao,
                'rota'       => 'team.php',
            ]);
        } catch (DadosPokemonInvalidosException | TimeInvalidoException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao salvar no banco de dados.', error: true);
        } catch (RuntimeException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        }
    }

    /** POST ?acao=remover&id_pokemon=12 */
    public function remover(): void
    {
        $idPokemon = (int) ($_POST['id_pokemon'] ?? $_GET['id_pokemon'] ?? 0);

        try {
            $this->timeModel->doPerfil(Auth::idPerfil());
            $removido = $this->timeModel->removerPokemon($idPokemon);

            $this->responder(
                $removido,
                $removido ? 'Pokémon removido do time.' : 'Este Pokémon não estava no time.',
                ['rota' => 'team.php'],
                error: !$removido
            );
        } catch (TimeInvalidoException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao remover o Pokémon.', error: true);
        }
    }

    /** POST ?acao=atualizarNivel&id_pokemon=12&nivel=5 */
    public function atualizarNivel(): void
    {
        $idPokemon = filter_var($_POST['id_pokemon'] ?? 0, FILTER_VALIDATE_INT);
        $nivel = filter_var($_POST['nivel'] ?? 0, FILTER_VALIDATE_INT);
        if ($idPokemon === false || $idPokemon <= 0 || $nivel === false || $nivel <= 0) {
            $this->responder(false, 'Informe um Pokémon e um nível válidos.', error: true);
            return;
        }

        try {
            $progresso = ProgressaoJogo::carregar(Database::conectar(), Auth::idPerfil());
            if ($nivel > $progresso['nivel_maximo']) {
                $this->responder(
                    false,
                    "O limite atual é nível {$progresso['nivel_maximo']}.",
                    error: true
                );
                return;
            }

            $this->timeModel->doPerfil(Auth::idPerfil());
            $atualizado = $this->timeModel->atualizarNivel($idPokemon, $nivel);
            $this->responder(
                $atualizado,
                $atualizado ? 'Nível atualizado.' : 'Esse Pokémon não está no seu time.',
                error: !$atualizado
            );
        } catch (TimeInvalidoException $e) {
            $this->responder(false, $e->getMessage(), error: true);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao atualizar o nível do Pokémon.', error: true);
        }
    }

    /** GET ?acao=listar -> devolve o time atual em JSON. */
    public function listar(): void
    {
        try {
            $this->timeModel->doPerfil(Auth::idPerfil());

            $this->responder(true, 'Time carregado.', [
                'time'       => $this->timeModel->getNome(),
                'maximo'     => Time::MAX_POKEMON,
                'total'      => count($this->timeModel->listarPokemons()),
                'pokemons'   => $this->timeModel->listarPokemons(),
            ]);
        } catch (PDOException $e) {
            $this->responder(false, 'Erro ao carregar o time.', error: true);
        }
    }

    // ---------- helper ----------

    /** Transporte de dados via JSON (requisito do trabalho). */
    private function responder(bool $sucesso, string $mensagem, array $extra = [], bool $error = false): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($error ? 400 : 200);

        echo json_encode(
            ['sucesso' => $sucesso, 'mensagem' => $mensagem] + $extra,
            JSON_UNESCAPED_UNICODE
        );
    }
}
