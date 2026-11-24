class BattleApp {
    constructor(endpoint) {
        this.endpoint = endpoint;
        this.progress = null;
        this.battle = null;
        this.team = [];
        this.movimentos = { jogador: [], oponente: [] };
        this.batalhaMovimentosCarregada = '';
        this.message = document.getElementById('mensagemBatalha');
        this.movePanel = document.getElementById('ataquesDisponiveis');

        document.getElementById('iniciarBatalha').addEventListener('click', () => this.iniciar());
        document.querySelectorAll('[data-acao]').forEach((button) => {
            button.addEventListener('click', () => this.jogar(button.dataset.acao));
        });
        document.getElementById('fugirBatalha').addEventListener('click', () => this.fugir());
        this.carregar();
    }

    definirArena(idBatalha) {
        const arenas = ['station', 'quiet-street', 'shopping-street'];
        document.body.dataset.arena = arenas[Number(idBatalha) % arenas.length];
    }

    async carregarPokemonsBatalha(jogador, oponente) {
        const chave = `${this.battle.id}:${jogador.id_pokedex}:${oponente.id_pokedex}`;
        if (this.batalhaMovimentosCarregada === chave) return;
        this.batalhaMovimentosCarregada = chave;
        this.movimentos = { jogador: [], oponente: [] };
        this.movePanel.replaceChildren();
        this.adicionarTextoAtaques('Carregando sprites e ataques da PokéAPI...');
        this.definirSprite(
            'jogadorSprite',
            [
                this.spriteUrl(jogador.id_pokedex, 'back'),
                this.spriteUrl(jogador.id_pokedex, 'front'),
            ],
            jogador.nome
        );
        this.definirSprite(
            'oponenteSprite',
            [
                this.spriteUrl(oponente.id_pokedex, 'front'),
                this.spriteUrl(oponente.id_pokedex, 'back'),
            ],
            oponente.nome
        );

        try {
            const [dadosJogador, dadosOponente] = await Promise.all([
                this.buscarPokemonApi(jogador.id_pokedex),
                this.buscarPokemonApi(oponente.id_pokedex),
            ]);
            if (this.batalhaMovimentosCarregada !== chave) return;

            this.definirSprite(
                'jogadorSprite',
                [
                    dadosJogador.sprites?.other?.showdown?.back_default,
                    dadosJogador.sprites?.back_default,
                    dadosJogador.sprites?.other?.['official-artwork']?.front_default,
                    dadosJogador.sprites?.front_default,
                    this.spriteUrl(jogador.id_pokedex, 'back'),
                    this.spriteUrl(jogador.id_pokedex, 'front'),
                ],
                jogador.nome
            );
            this.definirSprite(
                'oponenteSprite',
                [
                    dadosOponente.sprites?.other?.showdown?.front_default,
                    dadosOponente.sprites?.front_default,
                    dadosOponente.sprites?.other?.['official-artwork']?.front_default,
                    dadosOponente.sprites?.back_default,
                    this.spriteUrl(oponente.id_pokedex, 'front'),
                    this.spriteUrl(oponente.id_pokedex, 'back'),
                ],
                oponente.nome
            );

            const [movimentosJogador, movimentosOponente] = await Promise.all([
                this.buscarMovimentos(dadosJogador.moves),
                this.buscarMovimentos(dadosOponente.moves),
            ]);
            if (this.batalhaMovimentosCarregada !== chave) return;

            this.movimentos = { jogador: movimentosJogador, oponente: movimentosOponente };
            document.querySelectorAll('[data-acao]').forEach((button) => {
                button.disabled = movimentosOponente.length === 0;
            });
            this.renderizarAtaques();
        } catch (error) {
            if (this.batalhaMovimentosCarregada !== chave) return;
            document.querySelectorAll('[data-acao]').forEach((button) => {
                button.disabled = true;
            });
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Não foi possível carregar os ataques da PokéAPI. Verifique a conexão e recarregue a página.');
            this.mostrarMensagem(error.message || 'Falha ao carregar os dados da batalha.');
        }
    }

    async buscarPokemonApi(id) {
        const pokemonId = Number(id);
        if (!Number.isInteger(pokemonId) || pokemonId < 1 || pokemonId > 151) {
            throw new Error('O combate contém um Pokémon fora da primeira geração.');
        }
        const response = await fetch(`https://pokeapi.co/api/v2/pokemon/${pokemonId}`);
        if (!response.ok) throw new Error('A PokéAPI não respondeu ao carregar os sprites.');
        return response.json();
    }

    async buscarMovimentos(referencias) {
        const candidatos = [...(referencias || [])].filter((item) => item?.url && item?.name);
        for (let indice = candidatos.length - 1; indice > 0; indice--) {
            const aleatorio = Math.floor(Math.random() * (indice + 1));
            [candidatos[indice], candidatos[aleatorio]] = [candidatos[aleatorio], candidatos[indice]];
        }
        candidatos.length = Math.min(candidatos.length, 20);
        let falhas = 0;
        const resultados = await Promise.all(candidatos.map(async (referencia) => {
            try {
                const response = await fetch(referencia.url);
                if (!response.ok) {
                    falhas++;
                    return null;
                }
                const movimento = await response.json();
                if (
                    !Number.isInteger(movimento.power) || movimento.power < 1 || movimento.power > 250
                    || !['physical', 'special'].includes(movimento.damage_class?.name)
                    || (movimento.accuracy !== null && (!Number.isInteger(movimento.accuracy)
                        || movimento.accuracy < 1 || movimento.accuracy > 100))
                    || !/^[a-z-]{1,20}$/.test(movimento.type?.name || '')
                ) return null;
                return {
                    nome: movimento.name,
                    tipo: movimento.type?.name,
                    classe: movimento.damage_class.name,
                    poder: movimento.power,
                    precisao: Number.isInteger(movimento.accuracy) ? movimento.accuracy : null,
                    categoria: movimento.damage_class.name,
                };
            } catch (error) {
                falhas++;
                return null;
            }
        }));
        const movimentos = resultados.filter(Boolean).slice(0, 4);
        if (movimentos.length === 0 && falhas > 0) {
            throw new Error('A PokéAPI falhou ao carregar os ataques disponíveis.');
        }
        return movimentos;
    }

    renderizarAtaques() {
        this.movePanel.replaceChildren();
        if (this.movimentos.jogador.length === 0 || this.movimentos.oponente.length === 0) {
            this.adicionarTextoAtaques('Não há ataques de dano disponíveis para este Pokémon na PokéAPI.');
            return;
        }

        this.movimentos.jogador.forEach((movimento) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'move-button';
            button.disabled = !this.battle?.em_andamento;
            button.setAttribute('aria-label', `${this.formatarNomeAtaque(movimento.nome)}, poder ${movimento.poder}`);

            const nome = document.createElement('span');
            nome.className = 'move-name';
            nome.textContent = this.formatarNomeAtaque(movimento.nome);

            const meta = document.createElement('span');
            meta.className = 'move-meta';
            const poder = document.createElement('span');
            poder.textContent = `Poder ${movimento.poder}`;
            const tipo = document.createElement('span');
            tipo.className = `move-type type-${movimento.tipo}`;
            tipo.textContent = movimento.tipo;
            const detalhes = document.createElement('span');
            detalhes.className = 'move-details';
            const classe = movimento.classe === 'physical' ? 'Físico' : 'Especial';
            const precisao = movimento.precisao === null ? 'Não erra' : `${movimento.precisao}% precisão`;
            detalhes.textContent = `${classe} · ${precisao}`;
            meta.append(poder, document.createElement('br'), tipo, detalhes);
            button.append(nome, meta);
            button.addEventListener('click', () => this.jogar('atacar', movimento));
            this.movePanel.appendChild(button);
        });
    }

    formatarNomeAtaque(nome) {
        return nome.replace(/-/g, ' ');
    }

    adicionarTextoAtaques(texto) {
        const mensagem = document.createElement('p');
        mensagem.textContent = texto;
        this.movePanel.appendChild(mensagem);
    }

    definirSprite(id, url, nome) {
        const imagem = document.getElementById(id);
        imagem.onerror = null;
        const urls = (Array.isArray(url) ? url : [url]).filter(
            (item, indice, lista) => typeof item === 'string' && item !== '' && lista.indexOf(item) === indice
        );
        if (urls.length === 0) {
            imagem.removeAttribute('src');
            imagem.alt = '';
            imagem.hidden = true;
            return;
        }
        imagem.alt = `Sprite de ${nome}`;
        imagem.hidden = false;
        const tentarSprite = (indice) => {
            imagem.onerror = () => {
                if (indice + 1 < urls.length) {
                    tentarSprite(indice + 1);
                    return;
                }
                imagem.onerror = null;
                imagem.hidden = true;
                this.mostrarMensagem(`Não foi possível carregar o sprite de ${nome} pela PokéAPI.`);
            };
            imagem.src = urls[indice];
        };
        tentarSprite(0);
    }

    spriteUrl(id, lado) {
        const diretorio = lado === 'back' ? 'back/' : '';
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${diretorio}${Number(id)}.png`;
    }

    limparSprites() {
        ['jogadorSprite', 'oponenteSprite'].forEach((id) => this.definirSprite(id, '', ''));
    }

    async requisitar(action, values = {}) {
        const options = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (values) {
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
            options.body = new URLSearchParams(values);
        }

        const response = await fetch(`${this.endpoint}?acao=${encodeURIComponent(action)}`, options);
        const result = await response.json();
        if (!response.ok || !result.sucesso) {
            throw new Error(result.mensagem || 'Não foi possível concluir a ação.');
        }
        return result;
    }

    async carregar() {
        try {
            const result = await this.requisitar('estado', null);
            this.progress = result.progresso;
            this.battle = result.batalha;
            this.team = result.time;
            this.renderizar();
            this.mostrarMensagem('');
        } catch (error) {
            this.mostrarMensagem(error.message);
        }
    }

    async iniciar() {
        const idPokemon = document.getElementById('pokemonEscolhido').value;
        if (!idPokemon) {
            this.mostrarMensagem('Adicione um Pokémon ao seu time antes de batalhar.');
            return;
        }
        await this.executar('iniciar', { id_pokemon: idPokemon });
    }

    async jogar(action, movimento = null) {
        const values = {
            acao_batalha: action,
            movimentos_oponente: JSON.stringify(this.movimentos.oponente),
        };
        if (movimento) values.movimento = JSON.stringify(movimento);
        await this.executar('jogar', values);
    }

    async fugir() {
        if (!window.confirm('Encerrar esta batalha? Ela contará como derrota.')) return;
        await this.executar('fugir');
    }

    async executar(action, values = {}) {
        this.definirBotoes(true);
        try {
            await this.requisitar(action, values);
            await this.carregar();
        } catch (error) {
            this.mostrarMensagem(error.message);
            this.definirBotoes(false);
        }
    }

    renderizar() {
        this.renderizarProgresso();
        this.renderizarEquipe();
        this.renderizarBatalha();
    }

    renderizarProgresso() {
        if (!this.progress) return;
        const progress = this.progress;
        const restante = Math.max(0, progress.vitorias_necessarias - progress.vitorias);
        document.getElementById('limiteNivel').textContent = progress.nivel_maximo;
        document.getElementById('progressoTexto').textContent = progress.concluido
            ? 'A jornada foi concluída!'
            : `${progress.nome_area}: ${progress.vitorias}/${progress.vitorias_necessarias} vitórias; faltam ${restante}.`;

        const barra = document.getElementById('barraProgresso');
        barra.max = Math.max(1, progress.vitorias_necessarias);
        barra.value = progress.vitorias;

        const etapas = document.getElementById('listaEtapas');
        etapas.replaceChildren();
        const nomes = ['Área Inicial', 'Área 1', 'Área 2', 'Área 3', 'Área 4', 'Área 5', 'Elite dos 4'];
        nomes.forEach((nome, index) => {
            const item = document.createElement('li');
            item.textContent = nome;
            item.className = index < progress.area ? 'stage-complete' : '';
            if (index === progress.area && !progress.concluido) item.classList.add('stage-current');
            etapas.appendChild(item);
        });

        const desafio = document.getElementById('desafioTexto');
        desafio.textContent = progress.concluido
            ? 'Você venceu a Elite dos 4. Parabéns, treinador!'
            : progress.area === 6
                ? `Elite dos 4: batalha ${progress.vitorias + 1} de 4.`
                : `${progress.nome_area}: treinador ${progress.vitorias + 1} de ${progress.vitorias_necessarias}.`;
    }

    renderizarEquipe() {
        const select = document.getElementById('pokemonEscolhido');
        const valorAtual = select.value;
        select.replaceChildren();
        this.team.forEach((pokemon) => {
            const option = document.createElement('option');
            option.value = pokemon.id_pokemon;
            option.textContent = `${pokemon.nome} · Nv. ${pokemon.nivel} · ${pokemon.HP} HP base`;
            option.disabled = Number(pokemon.nivel) > Number(this.progress.nivel_maximo);
            select.appendChild(option);
        });
        if (this.team.some((pokemon) => String(pokemon.id_pokemon) === valorAtual)) {
            select.value = valorAtual;
        }
        if (this.team.length === 0) {
            const option = document.createElement('option');
            option.textContent = 'Nenhum Pokémon no time';
            option.value = '';
            select.appendChild(option);
        }
        document.getElementById('avisoEquipe').hidden = this.team.length > 0;

        document.getElementById('iniciarBatalha').disabled =
            this.team.length === 0 || Boolean(this.battle?.em_andamento) || this.progress.concluido;
    }

    renderizarBatalha() {
        const active = Boolean(this.battle?.em_andamento);
        const areaSetup = document.querySelector('.setup-panel .battle-setup');
        areaSetup.hidden = active || this.progress.concluido;
        document.querySelector('.progression-panel').hidden = active;

        document.querySelectorAll('[data-acao]').forEach((button) => {
            button.disabled = !active || this.movimentos.oponente.length === 0;
        });
        document.querySelectorAll('.move-button').forEach((button) => {
            button.disabled = !active;
        });
        document.getElementById('fugirBatalha').disabled = !active;

        if (active) {
            const state = this.battle.estado;
            this.renderizarCombatente('jogador', state.jogador);
            this.renderizarCombatente('oponente', state.oponente);
            document.getElementById('oponenteTreinador').textContent = this.battle.nome_oponente;
            this.definirArena(this.battle.id);
            this.carregarPokemonsBatalha(state.jogador, state.oponente);
        } else {
            this.limparCombatentes();
            document.getElementById('oponenteTreinador').textContent = this.battle?.nome_oponente || '';
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Inicie uma batalha para carregar os ataques.');
            this.batalhaMovimentosCarregada = '';
            this.movimentos = { jogador: [], oponente: [] };
            this.limparSprites();
            if (this.battle?.id) this.definirArena(this.battle.id);
        }

        const logs = document.getElementById('logsBatalha');
        logs.replaceChildren();
        (this.battle?.logs || []).forEach((log) => {
            const item = document.createElement('li');
            item.className = `log-${log.tipo}`;
            item.textContent = log.mensagem;
            logs.appendChild(item);
        });
        if (logs.children.length === 0) {
            const item = document.createElement('li');
            item.textContent = 'Os acontecimentos da batalha aparecerão aqui.';
            logs.appendChild(item);
        }
        logs.scrollTop = logs.scrollHeight;
        this.mostrarMensagem('');
    }

    renderizarCombatente(lado, combatente) {
        const nome = document.getElementById(`${lado}Nome`);
        nome.textContent = combatente.nome;
        nome.classList.add('capitalize');
        document.getElementById(`${lado}Nivel`).textContent = `Nível ${combatente.nivel}`;
        const hpBar = document.getElementById(`${lado}HpBar`);
        hpBar.max = combatente.hp_maximo;
        hpBar.value = combatente.hp;
        const percentualHp = combatente.hp / Math.max(1, combatente.hp_maximo);
        hpBar.classList.toggle('hp-warning', percentualHp <= 0.5 && percentualHp > 0.2);
        hpBar.classList.toggle('hp-critical', percentualHp <= 0.2);
        document.getElementById(`${lado}HpTexto`).textContent =
            `${combatente.hp}/${combatente.hp_maximo} HP (${Math.round(100 * percentualHp)}%)`;
        document.getElementById(`${lado}Estagios`).textContent =
            `ATQ ${this.formatarEstagio(combatente.estagio_ataque)} · DEF ${this.formatarEstagio(combatente.estagio_defesa)}`;
    }

    limparCombatentes() {
        ['jogador', 'oponente'].forEach((lado) => {
            document.getElementById(`${lado}Nome`).textContent = 'Aguardando...';
            document.getElementById(`${lado}Nivel`).textContent = '';
            document.getElementById(`${lado}HpBar`).value = 0;
            document.getElementById(`${lado}HpBar`).max = 1;
            document.getElementById(`${lado}HpTexto`).textContent = '—';
            document.getElementById(`${lado}Estagios`).textContent = '';
        });
    }

    formatarEstagio(value) {
        const stage = Number(value);
        return stage > 0 ? `+${stage}` : String(stage);
    }

    definirBotoes(disabled) {
        const active = Boolean(this.battle?.em_andamento);
        document.getElementById('iniciarBatalha').disabled =
            disabled || !this.team.length || active || Boolean(this.progress?.concluido);
        document.querySelectorAll('[data-acao]').forEach((button) => {
            button.disabled = disabled || !active || this.movimentos.oponente.length === 0;
        });
        document.getElementById('fugirBatalha').disabled = disabled || !active;
        document.querySelectorAll('.move-button').forEach((button) => {
            button.disabled = disabled || !active;
        });
    }

    mostrarMensagem(texto) {
        this.message.textContent = texto;
        this.message.hidden = texto === '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.battleApp = new BattleApp('../src/controller/roteadorBatalha.php');
});
