<?php
declare(strict_types=1);

namespace src\controller;

use PDO;
use PDOException;
use RuntimeException;
use config\Database;
use models\Batalha;
use models\ListaPokemonTreinadores;
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
                $batalha = [
                    'id' => $ativa['id_batalha'],
                    'area' => (int) $ativa['area'],
                    'numero_treinador' => (int) $ativa['treinador_numero'],
                    'nome_oponente' => $ativa['nome_oponente'],
                    'estado' => $ativa['estado'],
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

            $oponenteBase = ListaPokemonTreinadores::sortear($area);
            $nivelOponente = min($nivelMaximo, max($nivel, $nivelMaximo - random_int(0, 2)));
            $jogador = Batalha::combatente($pokemon, $nivel);
            $oponente = Batalha::combatente($oponenteBase, $nivelOponente);
            $nomeOponente = $this->nomeTreinador($area, $numeroTreinador);
            $estado = [
                'jogador' => $jogador,
                'oponente' => $oponente,
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
        if (!\in_array($acao, ['atacar', 'aumentar_ataque', 'reduzir_ataque'], true)) {
            $this->responder(false, 'Ação de batalha inválida.', status: 400);
            return;
        }

        try {
            $movimento = $acao === 'atacar'
                ? $this->lerMovimento((string) ($_POST['movimento'] ?? ''))
                : null;
            $movimentosOponente = $this->lerMovimentosOponente(
                (string) ($_POST['movimentos_oponente'] ?? '')
            );
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

            $idBatalha = $batalha['id_batalha'];
            $this->jogarAcao($idBatalha, $estado, $acao, $movimento);
            if ($estado['oponente']['hp'] <= 0) {
                $this->registrarVitoria($idPerfil, $batalha, $progresso);
            } else {
                $this->jogarTurnoOponente($idBatalha, $estado, $movimentosOponente);
                if ($estado['jogador']['hp'] <= 0) {
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
        ?array $movimento
    ): void
    {
        $jogador = &$estado['jogador'];
        $oponente = &$estado['oponente'];

        if ($acao === 'atacar') {
            if ($movimento === null) {
                throw new RuntimeException('Escolha um ataque da lista para continuar.');
            }
            $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
            if (!$this->movimentoAcertou($movimento)) {
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
                $movimento['classe']
            );
            $this->causarDano($idBatalha, $jogador, $oponente, $dano, $movimento);
            return;
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

    private function jogarTurnoOponente(int $idBatalha, array &$estado, array $movimentos): void
    {
        $oponente = &$estado['oponente'];
        $jogador = &$estado['jogador'];
        $movimento = $movimentos[random_int(0, count($movimentos) - 1)];
        $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
        if (!$this->movimentoAcertou($movimento)) {
            $this->batalhaDAO->registrarLog(
                $idBatalha,
                'miss',
                "{$oponente['nome']} usou {$nomeMovimento}, mas errou o ataque."
            );
            return;
        }

        $dano = Batalha::calcularDano(
            $oponente,
            $jogador,
            $movimento['poder'],
            $movimento['classe']
        );
        $this->causarDano($idBatalha, $oponente, $jogador, $dano, $movimento);
    }

    private function causarDano(
        int $idBatalha,
        array &$atacante,
        array &$defensor,
        int $dano,
        array $movimento
    ): void
    {
        $hpAnterior = (int) $defensor['hp'];
        $defensor['hp'] = max(0, $hpAnterior - $dano);
        $nomeMovimento = ucwords(str_replace('-', ' ', $movimento['nome']));
        $danoReal = $hpAnterior - (int) $defensor['hp'];
        $percentual = number_format(
            100 * $danoReal / max(1, (int) $defensor['hp_maximo']),
            1,
            ',',
            '.'
        );

        $this->batalhaDAO->registrarLog(
            $idBatalha,
            'damage',
            "{$atacante['nome']} usou {$nomeMovimento} ({$movimento['tipo']}) e causou {$danoReal} de dano em {$defensor['nome']} ({$percentual}% do HP máximo). HP: {$defensor['hp']}/{$defensor['hp_maximo']}."
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

        return $this->validarMovimento($movimento);
    }

    private function lerMovimentosOponente(string $json): array
    {
        $movimentos = json_decode($json, true);
        if (!is_array($movimentos) || $movimentos === [] || count($movimentos) > 4) {
            throw new RuntimeException('Não foi possível carregar os ataques do oponente. Recarregue a batalha.');
        }

        $validos = [];
        foreach ($movimentos as $movimento) {
            if (!is_array($movimento)) {
                throw new RuntimeException('Um dos ataques recebidos da PokéAPI é inválido.');
            }
            $validos[] = $this->validarMovimento($movimento);
        }

        return $validos;
    }

    private function validarMovimento(array $movimento): array
    {
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

    private function movimentoAcertou(array $movimento): bool
    {
        return $movimento['precisao'] === null
            || random_int(1, 100) <= $movimento['precisao'];
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