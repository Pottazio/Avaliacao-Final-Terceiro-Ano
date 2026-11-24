<?php
declare(strict_types=1);

namespace src\controller;

use PDO;
use PDOException;
use RuntimeException;
use config\Database;
use models\Batalha;
use models\MegaEvolucao;
use models\ProgressaoJogo;
use src\core\Auth;
use src\dao\BatalhaDAO;

class BattleController
{
    private PDO $pdo;
    private BatalhaDAO $batalhaDAO;

    public function __construct()
    {
        $this->pdo = Database::conectar();
        $this->batalhaDAO = new BatalhaDAO($this->pdo);
    }

    public function estado(): void
    {
        try {
            $idPerfil = Auth::idPerfil();
            $progresso = ProgressaoJogo::carregar($this->pdo, $idPerfil);
            $ativa = $this->batalhaDAO->buscarBatalhaAtiva($idPerfil);

            if ($ativa !== null) {
                $estado = $ativa['estado'];
                if (!isset($estado['reservas_jogador']) || !is_array($estado['reservas_jogador'])) {
                    $estado['reservas_jogador'] = $this->montarReservasJogador(
                        $idPerfil,
                        $estado['jogador'],
                        ProgressaoJogo::nivelMaximo((int) $ativa['area'])
                    );
                }
                $this->hidratarTiposEstado($estado);
                $estadoPublico = $estado;
                $estadoPublico['pokemons_oponente_restantes'] =
                    count($estado['reservas_oponente'] ?? [])
                    + ((int) ($estado['oponente']['hp'] ?? 0) > 0 ? 1 : 0);
                unset($estadoPublico['reservas_oponente'], $estadoPublico['pokemon_derrotados_oponente']);
                $batalha = [
                    'id' => $ativa['id_batalha'],
                    'area' => (int) $ativa['area'],
                    'numero_treinador' => (int) $ativa['treinador_numero'],
                    'nome_oponente' => $ativa['nome_oponente'],
                    'estado' => $estadoPublico,
                    'logs' => $this->batalhaDAO->listarLogs($ativa['id_batalha']),
                    'em_andamento' => true,
                ];
            } else {
                $ultima = $this->batalhaDAO->buscarUltimaBatalha($idPerfil);
                $batalha = $ultima === null ? null : [
                    'id' => (int) $ultima['id_batalha'],
                    'area' => (int) $ultima['area'],
                    'numero_treinador' => (int) $ultima['treinador_numero'],
                    'nome_oponente' => $ultima['nome_oponente'],
                    'resultado' => $ultima['resultado'],
                    'logs' => $this->batalhaDAO->listarLogs((int) $ultima['id_batalha']),
                    'em_andamento' => false,
                ];
            }

            $this->responder(true, 'Estado do jogo carregado.', [
                'progresso' => $progresso,
                'time' => $this->batalhaDAO->listarTime($idPerfil),
                'inventario' => $this->batalhaDAO->listarInventario($idPerfil),
                'ids_pokemon_excluidos_oponente' => $this->batalhaDAO->listarIdsPokemonExcluidosOponente(),
                'batalha' => $batalha,
            ]);
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível carregar o jogo. Confira se a migração de batalha foi aplicada.', status: 500);
        } catch (RuntimeException $e) {
            $this->responder(false, $e->getMessage(), status: 500);
        }
    }

    public function iniciar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Use POST para iniciar uma batalha.', status: 405);
            return;
        }

        $idPerfil = Auth::idPerfil();
        $idPokemon = filter_input(INPUT_POST, 'id_pokemon', FILTER_VALIDATE_INT);
        if ($idPokemon === false || $idPokemon === null || $idPokemon <= 0) {
            $this->responder(false, 'Escolha um Pokémon válido.', status: 400);
            return;
        }

        try {
            $dadosEquipeOponente = $this->lerDadosEquipeOponente(
                (string) ($_POST['equipe_oponente'] ?? '')
            );
        } catch (RuntimeException $e) {
            $this->responder(false, $e->getMessage(), status: 400);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $progresso = $this->batalhaDAO->obterProgresso($idPerfil, true);
            if ((bool) $progresso['concluido']) {
                $this->pdo->rollBack();
                $this->responder(false, 'Você já concluiu a jornada.', status: 409);
                return;
            }
            if ($this->batalhaDAO->buscarBatalhaAtiva($idPerfil, true) !== null) {
                $this->pdo->rollBack();
                $this->responder(false, 'Termine a batalha atual antes de iniciar outra.', status: 409);
                return;
            }

            $area = (int) $progresso['area_atual'];
            $configuracao = ProgressaoJogo::AREAS[$area] ?? ProgressaoJogo::AREAS[7];
            $numeroTreinador = $area === 6
                ? (int) $progresso['vitorias_elite'] + 1
                : (int) $progresso['vitorias_area'] + 1;
            if ($area >= 7 || $numeroTreinador > $configuracao['vitorias_necessarias']) {
                $this->pdo->rollBack();
                $this->responder(false, 'Não há mais treinadores disponíveis nesta etapa.', status: 409);
                return;
            }

            $pokemon = $this->batalhaDAO->buscarPokemonDoTime($idPerfil, $idPokemon);
            if ($pokemon === null) {
                $this->pdo->rollBack();
                $this->responder(false, 'Esse Pokémon não pertence ao seu time.', status: 403);
                return;
            }
            $nivelMaximo = ProgressaoJogo::nivelMaximo($area);
            $nivel = (int) $pokemon['nivel'];
            if ($nivel < 1 || $nivel > $nivelMaximo) {
                $this->pdo->rollBack();
                $this->responder(false, "O nível desse Pokémon excede o limite atual ({$nivelMaximo}).", status: 400);
                return;
            }

            $multiplicadorDificuldade = 1 + ($area * 0.03) + (($numeroTreinador - 1) * 0.005);
            $jogador = Batalha::combatente($pokemon, $nivel);
            $equipeOponente = $this->montarEquipeOponente(
                $idPerfil,
                $area,
                $configuracao,
                $dadosEquipeOponente,
                $multiplicadorDificuldade
            );
            $oponente = array_shift($equipeOponente);
            $nomeOponente = $this->nomeTreinador($area, $numeroTreinador);
            $estado = [
                'jogador' => $jogador,
                'oponente' => $oponente,
                'reservas_oponente' => $equipeOponente,
                'mega_utilizada' => false,
                'turno' => 0,
                'reservas_jogador' => $this->montarReservasJogador(
                    $idPerfil,
                    $jogador,
                    $nivelMaximo
                ),
            ];

            $idBatalha = $this->batalhaDAO->iniciar(
                $idPerfil,
                $area,
                $numeroTreinador,
                $nomeOponente,
                $estado
            );
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'info',
                "{$nomeOponente} enviou {$oponente['nome']} (Nv. {$oponente['nivel']})."
            );
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'info',
                "{$jogador['nome']} (Nv. {$nivel}) entrou na batalha com {$jogador['hp']}/{$jogador['hp_maximo']} HP."
            );

            $this->pdo->commit();
            $this->responder(true, 'Batalha iniciada.', ['id_batalha' => $idBatalha]);
        } catch (PDOException $e) {
            $this->reverterTransacao();
            $this->responder(false, 'Não foi possível iniciar a batalha. Confira se a migração foi aplicada.', status: 500);
        } catch (RuntimeException $e) {
            $this->reverterTransacao();
            $this->responder(false, $e->getMessage(), status: 400);
        }
    }

    public function acao(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Use POST para executar uma ação.', status: 405);
            return;
        }

        $acao = (string) ($_POST['acao_batalha'] ?? '');
        if (!\in_array($acao, ['atacar', 'trocar_pokemon', 'usar_item', 'responder_troca'], true)) {
            $this->responder(false, 'Ação de batalha inválida.', status: 400);
            return;
        }

        $idPokemonTroca = null;
        $idPokemonItem = null;
        $idItem = null;
        $idPokemonEntrada = null;
        $respostaTroca = null;
        if ($acao === 'responder_troca') {
            $respostaRecebida = $_POST['resposta_troca'] ?? '';
            if (!is_string($respostaRecebida) || !in_array($respostaRecebida, ['sim', 'nao'], true)) {
                $this->responder(false, 'Escolha se deseja trocar de Pokémon.', status: 400);
                return;
            }
            $respostaTroca = $respostaRecebida;
            if ($respostaTroca === 'sim') {
                $idPokemon = filter_var($_POST['id_pokemon'] ?? null, FILTER_VALIDATE_INT);
                if ($idPokemon === false || $idPokemon === null || $idPokemon <= 0) {
                    $this->responder(false, 'Escolha um Pokémon válido para entrar.', status: 400);
                    return;
                }
                $idPokemonEntrada = (int) $idPokemon;
            }
        }
        if ($acao === 'trocar_pokemon') {
            $idPokemon = filter_var($_POST['id_pokemon'] ?? null, FILTER_VALIDATE_INT);
            if ($idPokemon === false || $idPokemon === null || $idPokemon <= 0) {
                $this->responder(false, 'Escolha um Pokémon válido para trocar.', status: 400);
                return;
            }
            $idPokemonTroca = (int) $idPokemon;
        }
        if ($acao === 'usar_item') {
            $idItem = filter_var($_POST['id_item'] ?? null, FILTER_VALIDATE_INT);
            $idPokemon = filter_var($_POST['id_pokemon'] ?? null, FILTER_VALIDATE_INT);
            if ($idItem === false || $idItem === null || $idItem <= 0
                || $idPokemon === false || $idPokemon === null || $idPokemon <= 0) {
                $this->responder(false, 'Escolha um item e um Pokémon válidos.', status: 400);
                return;
            }
            $idItem = (int) $idItem;
            $idPokemonItem = (int) $idPokemon;
        }

        try {
            $movimento = $acao === 'atacar'
                ? $this->lerMovimento((string) ($_POST['movimento'] ?? ''))
                : null;
            $movimentosOponente = $acao === 'responder_troca'
                ? []
                : $this->lerListaMovimentos((string) ($_POST['movimentos_oponente'] ?? ''));
            $movimentosJogador = $movimento !== null && $movimento['nome'] === 'sleep-talk'
                ? $this->lerListaMovimentos((string) ($_POST['movimentos_jogador'] ?? ''))
                : [];
        } catch (RuntimeException $e) {
            $this->responder(false, $e->getMessage(), status: 400);
            return;
        }

        $idPerfil = Auth::idPerfil();
        try {
            $this->pdo->beginTransaction();
            $progresso = $this->batalhaDAO->obterProgresso($idPerfil, true);
            $batalha = $this->batalhaDAO->buscarBatalhaAtiva($idPerfil, true);
            if ($batalha === null) {
                $this->pdo->rollBack();
                $this->responder(false, 'Não há batalha em andamento.', status: 409);
                return;
            }

            $estado = $batalha['estado'];
            if (!isset($estado['jogador'], $estado['oponente'])) {
                throw new RuntimeException('O estado da batalha está incompleto.');
            }
            if (!isset($estado['reservas_jogador']) || !is_array($estado['reservas_jogador'])) {
                $estado['reservas_jogador'] = $this->montarReservasJogador(
                    $idPerfil,
                    $estado['jogador'],
                    ProgressaoJogo::nivelMaximo((int) $batalha['area'])
                );
            }
            $this->hidratarTiposEstado($estado);

            $idBatalha = $batalha['id_batalha'];
            $trocaPendente = !empty($estado['troca_oponente_pendente']);
            if ($acao === 'responder_troca') {
                if (!$trocaPendente || $respostaTroca === null) {
                    throw new RuntimeException('Não há uma troca de oponente aguardando resposta.');
                }

                if ($respostaTroca === 'sim') {
                    $indiceEntrada = null;
                    foreach ($estado['reservas_jogador'] as $indice => $reserva) {
                        if ((int) $reserva['id_pokemon'] === $idPokemonEntrada) {
                            if ((int) $reserva['hp'] <= 0) {
                                throw new RuntimeException('Esse Pokémon não pode mais batalhar.');
                            }
                            $indiceEntrada = $indice;
                            break;
                        }
                    }
                    if ($indiceEntrada === null) {
                        throw new RuntimeException('Esse Pokémon não está disponível para troca.');
                    }

                    $pokemonAnterior = $estado['jogador'];
                    $estado['jogador'] = $estado['reservas_jogador'][$indiceEntrada];
                    $estado['reservas_jogador'][$indiceEntrada] = $pokemonAnterior;
                    $this->batalhaDAO->registrarLog(
                        $idBatalha,
                        'switch',
                        "Você chamou {$estado['jogador']['nome']} para enfrentar {$estado['oponente']['nome']}."
                    );
                } else {
                    $this->batalhaDAO->registrarLog(
                        $idBatalha,
                        'switch',
                        "Você decidiu manter {$estado['jogador']['nome']} em campo."
                    );
                }

                $estado['troca_oponente_pendente'] = false;
                $this->batalhaDAO->salvarEstado($idBatalha, $estado);
                $this->pdo->commit();
                $this->responder(true, 'Decisão de troca registrada.');
                return;
            }
            if ($trocaPendente) {
                throw new RuntimeException('Responda à troca do treinador antes de continuar.');
            }

            $estado['turno'] = (int) ($estado['turno'] ?? 0) + 1;
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'turn',
                "Turno {$estado['turno']}"
            );
            if ($acao === 'atacar' && $movimento !== null) {
                $movimentoOponente = $movimentosOponente[
                    random_int(0, count($movimentosOponente) - 1)
                ];
                $ordemAtaques = Batalha::compararOrdemAtaques(
                    $movimento,
                    $estado['jogador'],
                    $movimentoOponente,
                    $estado['oponente']
                );
                $oponenteAgePrimeiro = $ordemAtaques < 0
                    || ($ordemAtaques === 0 && random_int(0, 1) === 0);

                if ($oponenteAgePrimeiro) {
                    $this->executarAtaque(
                        $idBatalha,
                        $estado,
                        'oponente',
                        $movimentoOponente,
                        $movimentosOponente
                    );
                    if ($estado['jogador']['hp'] > 0 && $estado['oponente']['hp'] > 0) {
                        $this->executarAtaque(
                            $idBatalha,
                            $estado,
                            'jogador',
                            $movimento,
                            $movimentosJogador
                        );
                    }
                } else {
                    $this->executarAtaque(
                        $idBatalha,
                        $estado,
                        'jogador',
                        $movimento,
                        $movimentosJogador
                    );
                    if ($estado['oponente']['hp'] > 0 && $estado['jogador']['hp'] > 0) {
                        $this->executarAtaque(
                            $idBatalha,
                            $estado,
                            'oponente',
                            $movimentoOponente,
                            $movimentosOponente
                        );
                    }
                }
            } else {
                $this->jogarAcao(
                    $idPerfil,
                    $idBatalha,
                    $estado,
                    $acao,
                    $movimento,
                    $idPokemonTroca,
                    $idItem,
                    $idPokemonItem
                );
                if ($estado['oponente']['hp'] > 0 && $estado['jogador']['hp'] > 0) {
                    $this->jogarTurnoOponente($idBatalha, $estado, $movimentosOponente);
                }
            }
            $this->aplicarDanoResidual($idBatalha, $estado);

            $jogadorPodeContinuar = $estado['jogador']['hp'] > 0
                || $this->trocarAutomaticamente($idBatalha, $estado);
            if (!$jogadorPodeContinuar) {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'result',
                    "{$estado['jogador']['nome']} não pode mais batalhar. {$batalha['nome_oponente']} venceu."
                );
                $this->batalhaDAO->finalizar($idPerfil, $idBatalha, 'DERROTA', 0);
            } elseif ($estado['oponente']['hp'] <= 0) {
                if ($this->prepararProximoOponente($idBatalha, $estado, $batalha['nome_oponente'])) {
                    $this->batalhaDAO->salvarEstado($idBatalha, $estado);
                } else {
                    $this->registrarVitoria($idPerfil, $batalha, $progresso);
                }
            } else {
                $this->batalhaDAO->salvarEstado($idBatalha, $estado);
            }

            $this->pdo->commit();
            $this->responder(true, 'Turno concluído.');
        } catch (PDOException $e) {
            $this->reverterTransacao();
            $this->responder(false, 'Não foi possível salvar o turno da batalha.', status: 500);
        } catch (RuntimeException $e) {
            $this->reverterTransacao();
            $this->responder(false, $e->getMessage(), status: 400);
        }
    }

    public function fugir(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Use POST para sair da batalha.', status: 405);
            return;
        }

        $idPerfil = Auth::idPerfil();
        try {
            $this->pdo->beginTransaction();
            $batalha = $this->batalhaDAO->buscarBatalhaAtiva($idPerfil, true);
            if ($batalha === null) {
                $this->pdo->rollBack();
                $this->responder(false, 'Não há batalha em andamento.', status: 409);
                return;
            }

            $this->batalhaDAO->registrarLog(
                $batalha['id_batalha'],
                'result',
                'Você fugiu da batalha. A vitória deste treinador ainda será necessária.'
            );
            $this->batalhaDAO->finalizar($idPerfil, $batalha['id_batalha'], 'DERROTA', 0);
            $this->pdo->commit();
            $this->responder(true, 'Você saiu da batalha.');
        } catch (PDOException $e) {
            $this->reverterTransacao();
            $this->responder(false, 'Não foi possível encerrar a batalha.', status: 500);
        } catch (RuntimeException $e) {
            $this->reverterTransacao();
            $this->responder(false, $e->getMessage(), status: 400);
        }
    }

    private function jogarAcao(
        int $idPerfil,
        int $idBatalha,
        array &$estado,
        string $acao,
        ?array $movimento,
        ?int $idPokemonTroca,
        ?int $idItem,
        ?int $idPokemonItem
    ): void
    {
        $jogador = &$estado['jogador'];

        if ($acao === 'atacar') {
            if ($movimento === null) {
                throw new RuntimeException('Escolha um ataque da lista para continuar.');
            }
            $this->executarAtaque($idBatalha, $estado, 'jogador', $movimento);
            return;
        }

        if ($acao === 'usar_item') {
            if ($idItem === null || $idPokemonItem === null) {
                throw new RuntimeException('Escolha um item e um Pokémon para continuar.');
            }
            $this->usarItem($idPerfil, $idBatalha, $estado, $idItem, $idPokemonItem);
            return;
        }

        if ($acao === 'trocar_pokemon') {
            if ($idPokemonTroca === null) {
                throw new RuntimeException('Escolha um Pokémon do time para trocar.');
            }

            foreach ($estado['reservas_jogador'] as $indice => $reserva) {
                if ((int) $reserva['id_pokemon'] !== $idPokemonTroca) {
                    continue;
                }
                if ((int) $reserva['hp'] <= 0) {
                    throw new RuntimeException('Esse Pokémon não pode mais batalhar.');
                }

                $pokemonAnterior = $jogador;
                $jogador = $reserva;
                $estado['reservas_jogador'][$indice] = $pokemonAnterior;
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'switch',
                    "Você chamou {$jogador['nome']} para a batalha."
                );
                return;
            }

            throw new RuntimeException('Esse Pokémon não está disponível para troca.');
        }

        throw new RuntimeException('Ação de batalha inválida.');
    }

    private function montarReservasJogador(
        int $idPerfil,
        array $jogadorAtivo,
        int $nivelMaximo
    ): array {
        $reservas = [];
        foreach ($this->batalhaDAO->listarTime($idPerfil) as $pokemon) {
            if ((int) $pokemon['id_pokemon'] === (int) $jogadorAtivo['id_pokemon']) {
                continue;
            }

            $nivel = (int) $pokemon['nivel'];
            if ($nivel < 1 || $nivel > $nivelMaximo) {
                continue;
            }
            $reservas[] = Batalha::combatente($pokemon, $nivel);
        }

        return $reservas;
    }

    private function lerDadosEquipeOponente(string $json): array
    {
        $equipe = json_decode($json, true);
        if (!is_array($equipe) || count($equipe) < 1 || count($equipe) > 6) {
            throw new RuntimeException('Não foi possível preparar a equipe adversária. Tente novamente.');
        }

        $tiposValidos = [
            'normal', 'fire', 'water', 'electric', 'grass', 'ice', 'fighting',
            'poison', 'ground', 'flying', 'psychic', 'bug', 'rock', 'ghost',
            'dragon', 'dark', 'steel', 'fairy',
        ];
        $equipeValidada = [];
        $idsPokemon = [];

        foreach ($equipe as $pokemon) {
            if (!is_array($pokemon)) {
                throw new RuntimeException('Os dados de um Pokémon adversário são inválidos.');
            }

            $idPokedex = filter_var($pokemon['id_pokedex'] ?? null, FILTER_VALIDATE_INT);
            $nome = $pokemon['name'] ?? null;
            $tipos = $pokemon['types'] ?? null;
            if (
                $idPokedex === false || $idPokedex === null
                || !is_string($nome) || !preg_match('/^[a-z0-9-]{1,60}$/', $nome)
                || !is_array($tipos) || count($tipos) < 1 || count($tipos) > 2
            ) {
                throw new RuntimeException('Os dados de um Pokémon adversário são inválidos.');
            }

            foreach ($tipos as $tipo) {
                if (!is_string($tipo)) {
                    throw new RuntimeException('Um Pokémon adversário tem um tipo inválido.');
                }
            }
            $tipos = array_values(array_unique(array_map(
                static fn (string $tipo): string => strtolower(trim($tipo)),
                $tipos
            )));
            if (array_diff($tipos, $tiposValidos) !== []) {
                throw new RuntimeException('Um Pokémon adversário tem um tipo inválido.');
            }

            if (in_array((int) $idPokedex, $idsPokemon, true)) {
                throw new RuntimeException('A equipe adversária não pode repetir Pokémon.');
            }
            $idsPokemon[] = (int) $idPokedex;

            $atributos = [];
            foreach ([
                'HP' => 'hp',
                'ATQ' => 'attack',
                'ATQE' => 'special-attack',
                'DEF' => 'defense',
                'DEFE' => 'special-defense',
                'SPE' => 'speed',
            ] as $atributo => $chave) {
                $valor = filter_var($pokemon[$chave] ?? null, FILTER_VALIDATE_INT);
                if ($valor === false || $valor < 1 || $valor > 255) {
                    throw new RuntimeException('Os atributos de um Pokémon adversário são inválidos.');
                }
                $atributos[$atributo] = $valor;
            }

            $equipeValidada[] = [
                'id_pokedex' => (int) $idPokedex,
                'name' => $nome,
                'tipos' => $tipos,
            ] + $atributos;
        }

        return $equipeValidada;
    }

    private function montarEquipeOponente(
        int $idPerfil,
        int $area,
        array $configuracao,
        array $dadosEquipe,
        float $multiplicadorDificuldade
    ): array {
        $quantidade = (int) $configuracao['pokemons_oponente'];
        if (count($dadosEquipe) !== $quantidade) {
            throw new RuntimeException('A quantidade de Pokémon adversários não corresponde a esta área.');
        }

        $idsPokemonDoJogador = array_map(
            static fn (array $pokemon): int => (int) $pokemon['id_pokedex'],
            $this->batalhaDAO->listarTime($idPerfil)
        );
        $equipe = [];
        foreach ($dadosEquipe as $pokemon) {
            $idPokedex = (int) $pokemon['id_pokedex'];
            if (
                $idPokedex < (int) $configuracao['pokedex_minimo']
                || $idPokedex > (int) $configuracao['pokedex_maximo']
                || !$this->batalhaDAO->pokemonElegivelOponente($idPokedex)
                || in_array($idPokedex, $idsPokemonDoJogador, true)
            ) {
                throw new RuntimeException('Um Pokémon adversário não pertence à Pokédex desta área.');
            }

            $nivel = random_int(
                (int) $configuracao['nivel_minimo'],
                (int) $configuracao['nivel_maximo']
            );
            $equipe[] = Batalha::combatente(
                $pokemon,
                $nivel,
                $multiplicadorDificuldade
            );
        }

        return $equipe;
    }

    private function trocarAutomaticamente(int $idBatalha, array &$estado): bool
    {
        foreach ($estado['reservas_jogador'] as $indice => $reserva) {
            if ((int) $reserva['hp'] <= 0) {
                continue;
            }

            $pokemonDerrotado = $estado['jogador'];
            $estado['jogador'] = $reserva;
            $estado['reservas_jogador'][$indice] = $pokemonDerrotado;
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'switch',
                "{$pokemonDerrotado['nome']} desmaiou. {$estado['jogador']['nome']} entrou na batalha."
            );
            return true;
        }

        return false;
    }

    private function prepararProximoOponente(
        int $idBatalha,
        array &$estado,
        string $nomeTreinador
    ): bool
    {
        if (empty($estado['reservas_oponente'])) {
            return false;
        }

        $estado['oponente'] = array_shift($estado['reservas_oponente']);
        $estado['troca_oponente_pendente'] = true;
        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'switch',
            "{$nomeTreinador} irá usar {$estado['oponente']['nome']} (Nv. {$estado['oponente']['nivel']}). Deseja trocar seu Pokémon em campo?"
        );
        return true;
    }

    private function hidratarTiposEstado(array &$estado): void
    {
        foreach (['jogador', 'oponente'] as $lado) {
            if (isset($estado[$lado]) && is_array($estado[$lado])) {
                $this->hidratarTiposPokemon($estado[$lado]);
            }
        }

        if (isset($estado['reservas_jogador']) && is_array($estado['reservas_jogador'])) {
            foreach ($estado['reservas_jogador'] as &$pokemon) {
                $this->hidratarTiposPokemon($pokemon);
            }
            unset($pokemon);
        }

        if (isset($estado['reservas_oponente']) && is_array($estado['reservas_oponente'])) {
            foreach ($estado['reservas_oponente'] as &$pokemon) {
                $this->hidratarTiposPokemon($pokemon);
            }
            unset($pokemon);
        }
    }

    private function hidratarTiposPokemon(array &$pokemon): void
    {
        if (!empty($pokemon['mega'])) {
            $mega = MegaEvolucao::porForma((string) ($pokemon['forma_mega'] ?? ''));
            if ($mega === null) {
                throw new RuntimeException('A forma Mega salva na batalha é inválida.');
            }
            $pokemon['tipos'] = $mega['tipos'];
        } elseif (!isset($pokemon['tipos']) || $pokemon['tipos'] === []) {
            $pokemon['tipos'] = $this->batalhaDAO->buscarTiposPokemon(
                (int) ($pokemon['id_pokedex'] ?? 0)
            );
        } elseif (is_string($pokemon['tipos'])) {
            $pokemon['tipos'] = explode(',', $pokemon['tipos']);
        }

        $pokemon['tipos'] = array_values(array_unique(array_filter(array_map(
            static fn ($tipo): string => strtolower(trim((string) $tipo)),
            (array) $pokemon['tipos']
        ))));
    }

    private function jogarTurnoOponente(int $idBatalha, array &$estado, array $movimentos): void
    {
        $movimento = $movimentos[random_int(0, count($movimentos) - 1)];
        $this->executarAtaque($idBatalha, $estado, 'oponente', $movimento, $movimentos);
    }

    private function executarAtaque(
        int $idBatalha,
        array &$estado,
        string $ladoAtacante,
        array $movimento,
        array $movimentosDisponiveis = []
    ): void {
        $ladoDefensor = $ladoAtacante === 'jogador' ? 'oponente' : 'jogador';
        $atacante = &$estado[$ladoAtacante];
        $defensor = &$estado[$ladoDefensor];
        $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
        $statusAtacante = strtoupper((string) ($atacante['status'] ?? 'NORMAL'));

        if ($movimento['nome'] === 'sleep-talk') {
            if ($statusAtacante !== 'SLEEP') {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} só pode usar Sleep Talk enquanto dorme."
                );
                return;
            }
            $opcoesSono = array_values(array_filter(
                $movimentosDisponiveis,
                static fn (array $disponivel): bool => $disponivel['nome'] !== 'sleep-talk'
            ));
            if ($opcoesSono === []) {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} não tinha outro movimento para Sleep Talk."
                );
                return;
            }
            if ((int) ($atacante['sleep_turns'] ?? 0) > 0) {
                $atacante['sleep_turns']--;
            }
            $movimento = $opcoesSono[random_int(0, count($opcoesSono) - 1)];
            $movimento['prioridade'] = 0;
            $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'info',
                "{$atacante['nome']} usou Sleep Talk e executou {$nomeMovimento}."
            );
        } elseif ($movimento['nome'] === 'snore') {
            if ($statusAtacante !== 'SLEEP') {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} só pode usar Snore enquanto dorme."
                );
                return;
            }
            if ((int) ($atacante['sleep_turns'] ?? 0) > 0) {
                $atacante['sleep_turns']--;
            }
        } elseif ($statusAtacante === 'FREEZE') {
            if ($movimento['tipo'] === 'fire' || $movimento['nome'] === 'scald') {
                $atacante['status'] = 'NORMAL';
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} descongelou ao usar {$nomeMovimento}."
                );
            } elseif (random_int(1, 100) <= 20) {
                $atacante['status'] = 'NORMAL';
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} descongelou e pode agir neste turno."
                );
            } else {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} está congelado e não conseguiu agir."
                );
                return;
            }
        } elseif ($statusAtacante === 'SLEEP') {
            $turnosSono = array_key_exists('sleep_turns', $atacante)
                ? max(0, (int) $atacante['sleep_turns'])
                : 1;
            if ($turnosSono > 0) {
                $atacante['sleep_turns'] = $turnosSono - 1;
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'status',
                    "{$atacante['nome']} continua dormindo e não conseguiu agir."
                );
                return;
            }
            $atacante['status'] = 'NORMAL';
            $atacante['sleep_turns'] = 0;
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$atacante['nome']} acordou e pode agir neste turno."
            );
        } elseif (
            $statusAtacante === 'PARALYSIS'
            && random_int(1, 100) <= 25
        ) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$atacante['nome']} está paralisado e não conseguiu agir."
            );
            return;
        }

        if (
            $movimento['precisao'] !== null
            && random_int(1, 100) > $movimento['precisao']
        ) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'miss',
                "{$atacante['nome']} usou {$nomeMovimento}, mas errou o ataque."
            );
            return;
        }

        if ($movimento['classe'] === 'status') {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'info',
                "{$atacante['nome']} usou {$nomeMovimento}."
            );
            $this->aplicarEfeitoStatus($idBatalha, $defensor, $movimento);
            return;
        }

        $dano = Batalha::calcularDano(
            $atacante,
            $defensor,
            $movimento['poder'],
            $movimento['classe'],
            $movimento['tipo']
        );
        $this->causarDano($idBatalha, $atacante, $defensor, $dano, $movimento);
        if ($dano <= 0 || $defensor['hp'] <= 0) {
            return;
        }

        if (
            $movimento['tipo'] === 'fire'
            && strtoupper((string) ($defensor['status'] ?? 'NORMAL')) === 'FREEZE'
        ) {
            $defensor['status'] = 'NORMAL';
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$defensor['nome']} descongelou ao ser atingido por {$nomeMovimento}."
            );
        }
        $this->aplicarEfeitoStatus($idBatalha, $defensor, $movimento);
    }

    private function aplicarEfeitoStatus(
        int $idBatalha,
        array &$defensor,
        array $movimento
    ): void {
        $status = $movimento['efeito_status'] ?? null;
        $chance = (int) ($movimento['chance_status'] ?? 0);
        if (
            !is_string($status)
            || !in_array($status, ['burn', 'freeze', 'paralysis', 'poison', 'sleep'], true)
            || $chance < 1
            || (int) $defensor['hp'] <= 0
            || strtoupper((string) ($defensor['status'] ?? 'NORMAL')) !== 'NORMAL'
        ) {
            return;
        }

        if (
            $movimento['classe'] === 'status'
            && Batalha::multiplicadorTipo($movimento['tipo'], $defensor['tipos'] ?? []) === 0.0
        ) {
            $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$defensor['nome']} não foi afetado por {$nomeMovimento}."
            );
            return;
        }

        $tiposImunes = [
            'burn' => ['fire'],
            'freeze' => ['ice'],
            'paralysis' => ['electric'],
            'poison' => ['poison', 'steel'],
            'sleep' => [],
        ];
        if (array_intersect($tiposImunes[$status], $defensor['tipos'] ?? []) !== []) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$defensor['nome']} é imune a essa condição de status."
            );
            return;
        }
        if (random_int(1, 100) > $chance) {
            return;
        }

        $rotulos = [
            'burn' => ['BURN', 'queimado'],
            'freeze' => ['FREEZE', 'congelado'],
            'paralysis' => ['PARALYSIS', 'paralisado'],
            'poison' => ['POISON', 'envenenado'],
            'sleep' => ['SLEEP', 'adormecido'],
        ];
        [$defensor['status'], $rotulo] = $rotulos[$status];
        $defensor['sleep_turns'] = $status === 'sleep' ? random_int(1, 3) : 0;
        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'status',
            "{$defensor['nome']} ficou {$rotulo}."
        );
    }

    private function aplicarDanoResidual(int $idBatalha, array &$estado): void
    {
        foreach (['jogador', 'oponente'] as $lado) {
            $pokemon = &$estado[$lado];
            $status = strtoupper((string) ($pokemon['status'] ?? 'NORMAL'));
            $divisor = match ($status) {
                'BURN' => 16,
                'POISON' => 8,
                default => null,
            };
            if ($divisor === null || (int) $pokemon['hp'] <= 0) {
                unset($pokemon);
                continue;
            }

            $dano = max(1, intdiv((int) $pokemon['hp_maximo'], $divisor));
            $pokemon['hp'] = max(0, (int) $pokemon['hp'] - $dano);
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'status',
                "{$pokemon['nome']} sofreu {$dano} de dano residual por "
                    . ($status === 'BURN' ? 'queimadura' : 'envenenamento')
                    . " ({$pokemon['hp']}/{$pokemon['hp_maximo']} HP)."
            );
            if ($pokemon['hp'] === 0) {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'faint',
                    "{$pokemon['nome']} não pode mais batalhar."
                );
            }
            unset($pokemon);
        }
    }

    private function usarItem(
        int $idPerfil,
        int $idBatalha,
        array &$estado,
        int $idItem,
        int $idPokemon
    ): void {
        $item = $this->batalhaDAO->buscarItemInventario($idPerfil, $idItem);
        if ($item === null || (int) $item['quantidade'] < 1) {
            throw new RuntimeException('Este item não está disponível na mochila.');
        }
        if ((bool) $item['evolucao']) {
            $this->usarMegaEvolucao($idBatalha, $estado, $item, $idPokemon);
            return;
        }

        if ((int) $estado['jogador']['id_pokemon'] === $idPokemon) {
            $alvo = &$estado['jogador'];
        } else {
            $alvo = null;
            foreach ($estado['reservas_jogador'] as &$reserva) {
                if ((int) $reserva['id_pokemon'] === $idPokemon) {
                    $alvo = &$reserva;
                    break;
                }
            }
            unset($reserva);
            if ($alvo === null) {
                throw new RuntimeException('Esse Pokémon não está disponível nesta batalha.');
            }
        }

        $hpAnterior = (int) $alvo['hp'];
        $hpMaximo = (int) $alvo['hp_maximo'];
        $statusCurado = false;
        $nomeItem = strtolower(trim((string) $item['nome']));

        if ((bool) $item['revive']) {
            if ($hpAnterior > 0) {
                throw new RuntimeException('Revive só pode ser usado em um Pokémon desmaiado.');
            }
            $alvo['hp'] = max(1, (int) ceil($hpMaximo / 2));
            $alvo['status'] = 'NORMAL';
            $alvo['sleep_turns'] = 0;
        } else {
            if ((int) $item['cura_status']) {
                $status = strtoupper((string) ($alvo['status'] ?? 'NORMAL'));
                $statusPorItem = [
                    'antidote' => 'POISON',
                    'burn heal' => 'BURN',
                    'ice heal' => 'FREEZE',
                    'awakening' => 'SLEEP',
                    'paralyze heal' => 'PARALYSIS',
                ];
                $statusEsperado = $statusPorItem[$nomeItem] ?? null;
                if ($status === 'NORMAL' || ($statusEsperado !== null && $status !== $statusEsperado)) {
                    if ($nomeItem !== 'full restore' || $hpAnterior >= $hpMaximo) {
                        throw new RuntimeException('Esse item não teria efeito neste Pokémon.');
                    }
                } else {
                    $alvo['status'] = 'NORMAL';
                    $alvo['sleep_turns'] = 0;
                    $statusCurado = true;
                }
            }

            if ($nomeItem === 'full restore' || (int) $item['valor_hp'] === 0 && !(bool) $item['cura_status']) {
                $alvo['hp'] = $hpMaximo;
            } elseif ((int) $item['valor_hp'] > 0) {
                $alvo['hp'] = min($hpMaximo, $hpAnterior + (int) $item['valor_hp']);
            }

            if ((int) $alvo['hp'] === $hpAnterior && !$statusCurado) {
                throw new RuntimeException('Esse item não teria efeito neste Pokémon.');
            }
        }

        $this->batalhaDAO->consumirItem($idPerfil, $idItem);
        $recuperado = (int) $alvo['hp'] - $hpAnterior;
        if ((bool) $item['revive']) {
            $mensagem = "{$alvo['nome']} foi revivido por {$item['nome']} e voltou com {$alvo['hp']}/{$hpMaximo} HP.";
        } elseif ($recuperado > 0 && $statusCurado) {
            $mensagem = "{$item['nome']} recuperou {$recuperado} HP de {$alvo['nome']} e curou seu status.";
        } elseif ($recuperado > 0) {
            $mensagem = "{$alvo['nome']} recuperou {$recuperado} HP com {$item['nome']} ({$alvo['hp']}/{$hpMaximo} HP).";
        } else {
            $mensagem = "{$item['nome']} curou o status de {$alvo['nome']}.";
        }

        $this->batalhaDAO->registrarLog($idBatalha, 'item', $mensagem);
        unset($alvo);
    }

    private function usarMegaEvolucao(
        int $idBatalha,
        array &$estado,
        array $item,
        int $idPokemon
    ): void {
        $nomeItem = (string) $item['nome'];
        if (MegaEvolucao::porItem($nomeItem) === null) {
            throw new RuntimeException('Este item de evolução não pode ser usado durante a batalha.');
        }

        $megaJaUsada = !empty($estado['mega_utilizada'])
            || !empty($estado['jogador']['mega']);
        foreach ($estado['reservas_jogador'] as $reserva) {
            $megaJaUsada = $megaJaUsada || !empty($reserva['mega']);
        }
        if ($megaJaUsada) {
            throw new RuntimeException('Você já usou sua Mega Evolução nesta batalha.');
        }

        if ((int) $estado['jogador']['id_pokemon'] === $idPokemon) {
            $alvo = &$estado['jogador'];
        } else {
            $alvo = null;
            foreach ($estado['reservas_jogador'] as &$reserva) {
                if ((int) $reserva['id_pokemon'] === $idPokemon) {
                    $alvo = &$reserva;
                    break;
                }
            }
            unset($reserva);
            if ($alvo === null) {
                throw new RuntimeException('Esse Pokémon não está disponível nesta batalha.');
            }
        }

        MegaEvolucao::aplicar($alvo, $nomeItem);
        $estado['mega_utilizada'] = true;
        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'item',
            "{$alvo['nome']} Mega Evoluiu para {$alvo['mega_nome']} usando {$nomeItem}. A pedra não foi consumida."
        );
        unset($alvo);
    }

    private function causarDano(
        int $idBatalha,
        array &$atacante,
        array &$defensor,
        int $dano,
        ?array $movimento = null,
        ?string $tipoAtaque = null
    ): void
    {
        $tipoAtaque = $movimento['tipo'] ?? $tipoAtaque;
        $multiplicadorTipo = $tipoAtaque === null
            ? 1.0
            : Batalha::multiplicadorTipo($tipoAtaque, $defensor['tipos'] ?? []);
        $descricaoAtaque = $movimento !== null
            ? "{$atacante['nome']} usou " . ucwords(str_replace('-', ' ', $movimento['nome']))
                . " ({$movimento['tipo']})"
            : ($tipoAtaque === null
                ? $atacante['nome']
                : "{$atacante['nome']} usou um ataque do tipo {$tipoAtaque}");

        if ($dano <= 0) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'damage',
                "{$descricaoAtaque}, mas não afetou {$defensor['nome']} (imune ao tipo {$tipoAtaque})."
            );
            return;
        }

        $hpAnterior = (int) $defensor['hp'];
        $defensor['hp'] = max(0, $hpAnterior - $dano);
        $danoReal = $hpAnterior - (int) $defensor['hp'];
        $percentual = number_format(
            100 * $danoReal / max(1, (int) $defensor['hp_maximo']),
            1,
            ',',
            '.'
        );

        $detalheAtaque = $movimento === null && $tipoAtaque === null
            ? "{$descricaoAtaque} causou"
            : "{$descricaoAtaque} e causou";
        $efetividade = $multiplicadorTipo > 1
            ? ' Foi super efetivo!'
            : ($multiplicadorTipo < 1 ? ' Não foi muito efetivo.' : '');

        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'damage',
            "{$detalheAtaque} {$danoReal} de dano em {$defensor['nome']} ({$percentual}% do HP máximo).{$efetividade} HP: {$defensor['hp']}/{$defensor['hp_maximo']}."
        );

        if ($defensor['hp'] === 0) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'faint',
                "{$defensor['nome']} não pode mais batalhar."
            );
        }
    }

    private function lerMovimento(string $json): array
    {
        $movimento = json_decode($json, true);
        if (!is_array($movimento)) {
            throw new RuntimeException('Os dados do ataque estão inválidos. Recarregue a batalha.');
        }

        $nome = (string) ($movimento['nome'] ?? '');
        $tipo = (string) ($movimento['tipo'] ?? '');
        $classe = (string) ($movimento['classe'] ?? '');
        $poder = filter_var($movimento['poder'] ?? 0, FILTER_VALIDATE_INT);
        $prioridade = filter_var($movimento['prioridade'] ?? 0, FILTER_VALIDATE_INT);
        $precisao = ($movimento['precisao'] ?? null) === null
            ? null
            : filter_var($movimento['precisao'], FILTER_VALIDATE_INT);
        $efeitoStatus = $movimento['efeito_status'] ?? null;
        $chanceStatus = filter_var($movimento['chance_status'] ?? 0, FILTER_VALIDATE_INT);
        $statusValidos = ['burn', 'freeze', 'paralysis', 'poison', 'sleep'];
        $sleepTalk = $nome === 'sleep-talk';

        if (
            !preg_match('/^[a-z0-9-]{1,60}$/', $nome)
            || !preg_match('/^[a-z-]{1,20}$/', $tipo)
            || !in_array($classe, ['physical', 'special', 'status'], true)
            || $poder === false || $poder < 0 || $poder > 250
            || $prioridade === false || $prioridade < -7 || $prioridade > 5
            || ($precisao !== null && ($precisao === false || $precisao < 1 || $precisao > 100))
            || $chanceStatus === false || $chanceStatus < 0 || $chanceStatus > 100
            || ($efeitoStatus !== null && (
                !is_string($efeitoStatus) || !in_array($efeitoStatus, $statusValidos, true)
            ))
            || ($classe === 'status' && !$sleepTalk && (
                $poder !== 0 || !is_string($efeitoStatus) || $chanceStatus < 1
            ))
            || ($sleepTalk && (
                $classe !== 'status' || $poder !== 0 || $efeitoStatus !== null || $chanceStatus !== 0
            ))
            || ($classe !== 'status' && ($poder < 1 || ($chanceStatus > 0 && $efeitoStatus === null)))
        ) {
            throw new RuntimeException('Um dos ataques recebidos da PokéAPI é inválido.');
        }

        return [
            'nome' => $nome,
            'tipo' => $tipo,
            'classe' => $classe,
            'poder' => $poder,
            'precisao' => $precisao,
            'prioridade' => $prioridade,
            'efeito_status' => $efeitoStatus,
            'chance_status' => $chanceStatus,
        ];
    }

    private function lerListaMovimentos(string $json): array
    {
        $movimentos = json_decode($json, true);
        if (!is_array($movimentos) || count($movimentos) < 1 || count($movimentos) > 4) {
            throw new RuntimeException('A lista de movimentos não foi carregada. Recarregue a batalha.');
        }

        $validos = [];
        foreach ($movimentos as $movimento) {
            if (!is_array($movimento)) {
                throw new RuntimeException('A lista de ataques do oponente é inválida.');
            }
            $validos[] = $this->lerMovimento(
                json_encode($movimento, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
        }
        return $validos;
    }

    private function registrarVitoria(int $idPerfil, array $batalha, array $progresso): void
    {
        $idBatalha = (int) $batalha['id_batalha'];
        $area = (int) $progresso['area_atual'];
        $recompensa = $area === 6 ? 500 : 100 + $area * 50;

        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'result',
            "Você venceu {$batalha['nome_oponente']}! Recompensa: {$recompensa} pokédollars."
        );

        if ($area === 6) {
            $progresso['vitorias_elite'] = (int) $progresso['vitorias_elite'] + 1;
            if ((int) $progresso['vitorias_elite'] >= ProgressaoJogo::AREAS[6]['vitorias_necessarias']) {
                $progresso['area_atual'] = 7;
                $progresso['concluido'] = 1;
                $this->batalhaDAO->registrarLog($idBatalha, 'result', 'Você venceu a Elite dos 4 e concluiu a jornada!');
            }
        } else {
            $progresso['vitorias_area'] = (int) $progresso['vitorias_area'] + 1;
            if ((int) $progresso['vitorias_area'] >= ProgressaoJogo::AREAS[$area]['vitorias_necessarias']) {
                $progresso['area_atual'] = $area + 1;
                $progresso['vitorias_area'] = 0;
                $proximaArea = ProgressaoJogo::AREAS[$area + 1]['nome'];
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'result',
                    "Desafio concluído! Você avançou para {$proximaArea}."
                );
            }
        }

        $this->batalhaDAO->atualizarProgresso($idPerfil, $progresso);
        $this->batalhaDAO->finalizar($idPerfil, $idBatalha, 'VITORIA', $recompensa);
    }

    private function nomeTreinador(int $area, int $numero): string
    {
        if ($area === 6) {
            return ['Elite do Gelo', 'Elite da Luta', 'Elite Fantasma', 'Campeão Dragão'][$numero - 1] ?? 'Elite dos 4';
        }

        $nomes = ['Lia', 'Bento', 'Maya', 'Ravi', 'Nina', 'Caio', 'Iris', 'Noah', 'Luna', 'Theo'];
        $nome = $nomes[($numero - 1) % \count($nomes)];
        return "Treinador {$nome} ({$numero})";
    }

    private function reverterTransacao(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function responder(bool $sucesso, string $mensagem, array $dados = [], int $status = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(
            ['sucesso' => $sucesso, 'mensagem' => $mensagem] + $dados,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}