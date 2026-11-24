<?php
declare(strict_types=1);

namespace src\controller;

use PDO;
use PDOException;
use RuntimeException;
use config\Database;
use models\Batalha;
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
                $batalha = [
                    'id' => $ativa['id_batalha'],
                    'area' => (int) $ativa['area'],
                    'numero_treinador' => (int) $ativa['treinador_numero'],
                    'nome_oponente' => $ativa['nome_oponente'],
                    'estado' => $estado,
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

            $oponenteBase = $this->batalhaDAO->buscarPokemonOponente(
                (int) $pokemon['id_pokedex'],
                $area
            );
            $nivelOponente = min($nivelMaximo, max($nivel, $nivelMaximo - random_int(0, 2)));
            $multiplicadorDificuldade = 1 + ($area * 0.03) + (($numeroTreinador - 1) * 0.005);
            $jogador = Batalha::combatente($pokemon, $nivel);
            $oponente = Batalha::combatente(
                $oponenteBase,
                $nivelOponente,
                $multiplicadorDificuldade
            );
            $nomeOponente = $this->nomeTreinador($area, $numeroTreinador);
            $estado = [
                'jogador' => $jogador,
                'oponente' => $oponente,
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
                "{$nomeOponente} enviou {$oponente['nome']} (Nv. {$nivelOponente})."
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
        if (!\in_array($acao, ['atacar', 'aumentar_ataque', 'reduzir_ataque', 'trocar_pokemon'], true)) {
            $this->responder(false, 'Ação de batalha inválida.', status: 400);
            return;
        }

        $idPokemonTroca = null;
        if ($acao === 'trocar_pokemon') {
            $idPokemon = filter_input(INPUT_POST, 'id_pokemon', FILTER_VALIDATE_INT);
            if ($idPokemon === false || $idPokemon === null || $idPokemon <= 0) {
                $this->responder(false, 'Escolha um Pokémon válido para trocar.', status: 400);
                return;
            }
            $idPokemonTroca = (int) $idPokemon;
        }

        try {
            $movimento = $acao === 'atacar'
                ? $this->lerMovimento((string) ($_POST['movimento'] ?? ''))
                : null;
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
            $this->jogarAcao($idBatalha, $estado, $acao, $movimento, $idPokemonTroca);
            if ($estado['oponente']['hp'] <= 0) {
                $this->registrarVitoria($idPerfil, $batalha, $progresso);
            } else {
                $this->jogarTurnoOponente($idBatalha, $estado);
                if (
                    $estado['jogador']['hp'] <= 0
                    && !$this->trocarAutomaticamente($idBatalha, $estado)
                ) {
                    $this->batalhaDAO->registrarLog(
                        $idBatalha,
                        'result',
                        "{$estado['jogador']['nome']} não pode mais batalhar. {$batalha['nome_oponente']} venceu."
                    );
                    $this->batalhaDAO->finalizar($idPerfil, $idBatalha, 'DERROTA', 0);
                } else {
                    $this->batalhaDAO->salvarEstado($idBatalha, $estado);
                }
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
        int $idBatalha,
        array &$estado,
        string $acao,
        ?array $movimento,
        ?int $idPokemonTroca
    ): void
    {
        $jogador = &$estado['jogador'];
        $oponente = &$estado['oponente'];

        if ($acao === 'atacar') {
            if ($movimento === null) {
                throw new RuntimeException('Escolha um ataque da lista para continuar.');
            }
            $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
            if (
                $movimento['precisao'] !== null
                && random_int(1, 100) > $movimento['precisao']
            ) {
                $this->batalhaDAO->registrarLog(
                    $idBatalha,
                    'miss',
                    "{$jogador['nome']} usou {$nomeMovimento}, mas errou o ataque."
                );
                return;
            }

            $dano = Batalha::calcularDano(
                $jogador,
                $oponente,
                $movimento['poder'],
                $movimento['classe'],
                $movimento['tipo']
            );
            $this->causarDano($idBatalha, $jogador, $oponente, $dano, $movimento);
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

        if ($acao === 'aumentar_ataque') {
            $anterior = (int) $jogador['estagio_ataque'];
            $jogador['estagio_ataque'] = min(6, $anterior + 1);
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'boost',
                $jogador['estagio_ataque'] === $anterior
                    ? "{$jogador['nome']} não pode aumentar mais o ATQ."
                    : "O ATQ de {$jogador['nome']} subiu 1 estágio ({$anterior} → {$jogador['estagio_ataque']})."
            );
            return;
        }

        $anterior = (int) $oponente['estagio_ataque'];
        $oponente['estagio_ataque'] = max(-6, $anterior - 1);
        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'boost',
            $oponente['estagio_ataque'] === $anterior
                ? "O ATQ de {$oponente['nome']} não pode diminuir mais."
                : "O ATQ de {$oponente['nome']} caiu 1 estágio ({$anterior} → {$oponente['estagio_ataque']})."
        );
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
    }

    private function hidratarTiposPokemon(array &$pokemon): void
    {
        if (!isset($pokemon['tipos']) || $pokemon['tipos'] === []) {
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

    private function jogarTurnoOponente(int $idBatalha, array &$estado): void
    {
        $oponente = &$estado['oponente'];
        $jogador = &$estado['jogador'];

        if ($oponente['estagio_ataque'] < 2 && random_int(1, 4) === 1) {
            $anterior = (int) $oponente['estagio_ataque'];
            $oponente['estagio_ataque']++;
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'boost',
                "O ATQ de {$oponente['nome']} subiu 1 estágio ({$anterior} → {$oponente['estagio_ataque']})."
            );
            return;
        }

        $tipoAtaque = $oponente['tipos'][0] ?? null;
        $dano = Batalha::calcularDano(
            $oponente,
            $jogador,
            40,
            'physical',
            $tipoAtaque
        );
        $this->causarDano($idBatalha, $oponente, $jogador, $dano, tipoAtaque: $tipoAtaque);
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
        $poder = filter_var($movimento['poder'] ?? null, FILTER_VALIDATE_INT);
        $precisao = ($movimento['precisao'] ?? null) === null
            ? null
            : filter_var($movimento['precisao'], FILTER_VALIDATE_INT);

        if (
            !preg_match('/^[a-z0-9-]{1,60}$/', $nome)
            || !preg_match('/^[a-z-]{1,20}$/', $tipo)
            || !in_array($classe, ['physical', 'special'], true)
            || $poder === false || $poder < 1 || $poder > 250
            || ($precisao !== null && ($precisao === false || $precisao < 1 || $precisao > 100))
        ) {
            throw new RuntimeException('Um dos ataques recebidos da PokéAPI é inválido.');
        }

        return [
            'nome' => $nome,
            'tipo' => $tipo,
            'classe' => $classe,
            'poder' => $poder,
            'precisao' => $precisao,
        ];
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