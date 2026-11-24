class BattleApp {
    constructor(endpoint) {
        this.endpoint = endpoint;
        this.progress = null;
        this.battle = null;
        this.team = [];
        this.movimentos = { jogador: [] };
        this.batalhaMovimentosCarregada = '';
        this.cacheMovimentos = new Map();
        this.message = document.getElementById('mensagemBatalha');
        this.movePanel = document.getElementById('ataquesDisponiveis');
        this.switchPanel = document.getElementById('equipeBatalha');
        this.switchList = document.getElementById('opcoesPokemonTime');

        document.getElementById('iniciarBatalha').addEventListener('click', () => this.iniciar());
        document.querySelectorAll('[data-acao]').forEach((button) => {
            button.addEventListener('click', () => this.jogar(button.dataset.acao));
        });
        document.getElementById('fugirBatalha').addEventListener('click', () => this.fugir());
        this.carregar();
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
        const values = { acao_batalha: action };
        if (movimento) values.movimento = JSON.stringify(movimento);
        await this.executar('jogar', values);
    }

    async trocarPokemon(idPokemon) {
        await this.executar('jogar', {
            acao_batalha: 'trocar_pokemon',
            id_pokemon: idPokemon,
        });
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
            this.renderizarBatalha();
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
        document.querySelector('.setup-panel').hidden = active || this.progress.concluido;
        document.querySelector('.progression-panel').hidden = active;

        const controls = document.querySelectorAll('[data-acao], #fugirBatalha');
        controls.forEach((button) => {
            button.disabled = !active;
        });
        this.switchPanel.hidden = !active;

        if (active) {
            const state = this.battle.estado;
            this.renderizarCombatente('jogador', state.jogador);
            this.renderizarCombatente('oponente', state.oponente);
            this.renderizarOpcoesTroca(state);
            document.getElementById('oponenteTreinador').textContent = this.battle.nome_oponente;
            this.carregarPokemonsBatalha(state.jogador, state.oponente);
        } else {
            this.switchList.replaceChildren();
            this.limparCombatentes();
            document.getElementById('oponenteTreinador').textContent = this.battle?.nome_oponente || '';
            this.batalhaMovimentosCarregada = '';
            this.movimentos = { jogador: [] };
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Inicie uma batalha para carregar os ataques.');
        }

        document.querySelectorAll('.move-button').forEach((button) => {
            button.disabled = !active || this.movimentos.jogador.length === 0;
        });

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
    }

    renderizarOpcoesTroca(state) {
        this.switchList.replaceChildren();
        const reservas = new Map(
            (state.reservas_jogador || []).map((pokemon) => [Number(pokemon.id_pokemon), pokemon])
        );
        const disponiveis = this.team.filter(
            (pokemon) => Number(pokemon.id_pokemon) !== Number(state.jogador.id_pokemon)
        );

        if (disponiveis.length === 0) {
            const texto = document.createElement('p');
            texto.textContent = 'Não há outros Pokémon no seu time.';
            this.switchList.appendChild(texto);
            return;
        }

        disponiveis.forEach((pokemon) => {
            const reserva = reservas.get(Number(pokemon.id_pokemon));
            const hpMaximo = Number(reserva?.hp_maximo || pokemon.HP || 1);
            const hp = Math.max(0, Math.min(hpMaximo, Number(reserva?.hp ?? hpMaximo)));
            const nivelValido = Number(pokemon.nivel) <= Number(this.progress.nivel_maximo);
            const podeTrocar = Boolean(reserva) && hp > 0 && nivelValido;
            const button = document.createElement('button');
            button.className = 'switch-button';
            button.type = 'button';
            button.disabled = !podeTrocar;
            button.title = !nivelValido
                ? 'Este Pokémon excede o nível máximo da área.'
                : hp <= 0
                    ? 'Este Pokémon está desmaiado.'
                    : !reserva
                        ? 'Este Pokémon não está disponível nesta batalha.'
                        : 'Trocar por este Pokémon (consome seu turno).';
            button.addEventListener('click', () => this.trocarPokemon(pokemon.id_pokemon));

            const sprite = document.createElement('img');
            sprite.className = 'switch-sprite';
            sprite.src = this.spriteUrl(Number(pokemon.id_pokedex), 'front');
            sprite.alt = '';
            sprite.width = 64;
            sprite.height = 64;
            sprite.loading = 'lazy';
            button.appendChild(sprite);

            const details = document.createElement('span');
            details.className = 'switch-details';
            const name = document.createElement('span');
            name.className = 'switch-name';
            name.textContent = `${pokemon.nome} · Nv. ${pokemon.nivel}`;
            details.appendChild(name);

            const hpBar = document.createElement('progress');
            hpBar.className = 'switch-hp-bar';
            hpBar.max = hpMaximo;
            hpBar.value = hp;
            details.appendChild(hpBar);

            const hpText = document.createElement('span');
            hpText.className = 'switch-hp-text';
            hpText.textContent = `HP: ${hp} / ${hpMaximo} · ${Math.round(100 * hp / hpMaximo)}%`;
            details.appendChild(hpText);
            button.appendChild(details);
            this.switchList.appendChild(button);
        });
    }

    async carregarPokemonsBatalha(jogador, oponente) {
        const identificadorJogador = this.identificarPokemon(jogador, 'jogador');
        const identificadorOponente = this.identificarPokemon(oponente, 'oponente');
        const chave = `${this.battle.id}:${identificadorJogador}:${identificadorOponente}`;
        if (this.batalhaMovimentosCarregada === chave) return;
        this.batalhaMovimentosCarregada = chave;
        this.movimentos = { jogador: [] };
        this.movePanel.replaceChildren();
        this.adicionarTextoAtaques('Carregando sprites e ataques da PokéAPI...');
        this.definirTamanhoSprite('jogadorSprite', null);
        this.definirTamanhoSprite('oponenteSprite', null);

        if (Number.isInteger(Number(identificadorJogador))) {
            this.definirSprite(
                'jogadorSprite',
                [this.spriteUrl(identificadorJogador, 'back'), this.spriteUrl(identificadorJogador, 'front')],
                jogador.nome
            );
        }

        try {
            const [dadosJogador, dadosOponente] = await Promise.all([
                this.buscarPokemonApi(identificadorJogador),
                this.buscarPokemonApi(identificadorOponente),
            ]);
            if (this.batalhaMovimentosCarregada !== chave) return;

            this.definirTamanhoSprite('jogadorSprite', dadosJogador.height);
            this.definirTamanhoSprite('oponenteSprite', dadosOponente.height);
            this.definirSprite(
                'jogadorSprite',
                [
                    dadosJogador.sprites?.other?.showdown?.back_default,
                    dadosJogador.sprites?.back_default,
                    dadosJogador.sprites?.other?.['official-artwork']?.front_default,
                    dadosJogador.sprites?.front_default,
                    this.spriteUrl(dadosJogador.id, 'back'),
                    this.spriteUrl(dadosJogador.id, 'front'),
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
                    this.spriteUrl(dadosOponente.id, 'front'),
                    this.spriteUrl(dadosOponente.id, 'back'),
                ],
                oponente.nome
            );

            const nivelMaximoGolpes = this.progress?.area === 0
                ? 20
                : Number(jogador.nivel);
            this.movimentos.jogador = await this.buscarMovimentos(
                dadosJogador.moves,
                nivelMaximoGolpes
            );
            if (this.batalhaMovimentosCarregada !== chave) return;
            this.renderizarAtaques();
            document.querySelectorAll('[data-acao], #fugirBatalha').forEach((button) => {
                button.disabled = !this.battle?.em_andamento;
            });
        } catch (error) {
            if (this.batalhaMovimentosCarregada !== chave) return;
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Não foi possível carregar os ataques da PokéAPI. Verifique a conexão e recarregue a página.');
            this.mostrarMensagem(error.message || 'Falha ao carregar os dados da batalha.');
            document.querySelectorAll('[data-acao], #fugirBatalha').forEach((button) => {
                button.disabled = !this.battle?.em_andamento;
            });
        }
    }

    identificarPokemon(combatente, lado) {
        const idExistente = Number(combatente.id_pokedex);
        if (Number.isInteger(idExistente) && idExistente > 0) return idExistente;

        if (lado === 'jogador') {
            const pokemonDoTime = this.team.find(
                (pokemon) => Number(pokemon.id_pokemon) === Number(combatente.id_pokemon)
            );
            const idDoTime = Number(pokemonDoTime?.id_pokedex);
            if (Number.isInteger(idDoTime) && idDoTime > 0) return idDoTime;
            return String(pokemonDoTime?.especie || pokemonDoTime?.nome || combatente.nome)
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '-');
        }

        return String(combatente.nome).toLowerCase().trim().replace(/\s+/g, '-');
    }

    async buscarPokemonApi(identificador) {
        const nomeOuId = String(identificador).toLowerCase();
        if (
            !/^(?:[1-9]\d{0,3}|[a-z0-9-]{1,60})$/.test(nomeOuId)
            || (/^\d+$/.test(nomeOuId) && Number(nomeOuId) > 1025)
        ) {
            throw new Error('Não foi possível identificar este Pokémon na PokéAPI.');
        }
        const response = await fetch(`https://pokeapi.co/api/v2/pokemon/${encodeURIComponent(nomeOuId)}`);
        if (!response.ok) throw new Error('A PokéAPI não respondeu ao carregar os sprites.');
        return response.json();
    }

    async buscarMovimentos(referencias, nivelMaximo) {
        const candidatos = [...(referencias || [])].filter((item) => {
            if (!item?.move?.url || !item?.move?.name || !Array.isArray(item.version_group_details)) {
                return false;
            }

            return item.version_group_details.some((detalhe) => (
                detalhe.move_learn_method?.name === 'level-up'
                && Number.isInteger(detalhe.level_learned_at)
                && detalhe.level_learned_at <= nivelMaximo
            ));
        });
        for (let indice = candidatos.length - 1; indice > 0; indice--) {
            const aleatorio = Math.floor(Math.random() * (indice + 1));
            [candidatos[indice], candidatos[aleatorio]] = [candidatos[aleatorio], candidatos[indice]];
        }

        const converterMovimento = (movimento) => {
            if (
                !Number.isInteger(movimento.power) || movimento.power < 1 || movimento.power > 250
                || !['physical', 'special'].includes(movimento.damage_class?.name)
                || (movimento.accuracy !== null && (!Number.isInteger(movimento.accuracy)
                    || movimento.accuracy < 1 || movimento.accuracy > 100))
                || !/^[a-z-]{1,20}$/.test(movimento.type?.name || '')
            ) return null;
            return {
                nome: movimento.name,
                tipo: movimento.type.name,
                classe: movimento.damage_class.name,
                poder: movimento.power,
                precisao: Number.isInteger(movimento.accuracy) ? movimento.accuracy : null,
            };
        };

        let falhas = 0;
        const carregarMovimento = async (referencia) => {
            const url = referencia.move.url;
            if (this.cacheMovimentos.has(url)) {
                return this.cacheMovimentos.get(url);
            }

            for (let tentativa = 0; tentativa < 2; tentativa++) {
                try {
                    const response = await fetch(url);
                    if (response.ok) {
                        const movimento = converterMovimento(await response.json());
                        this.cacheMovimentos.set(url, movimento);
                        return movimento;
                    }
                } catch {
                    if (tentativa === 1) {
                        falhas++;
                        return null;
                    }
                }
                if (tentativa === 0) {
                    await new Promise((resolve) => setTimeout(resolve, 200));
                }
            }
            falhas++;
            return null;
        };

        const movimentos = [];
        const limite = Math.min(candidatos.length, 80);
        for (let inicio = 0; inicio < limite && movimentos.length < 4; inicio += 4) {
            const resultados = await Promise.all(candidatos.slice(inicio, inicio + 4).map(
                carregarMovimento
            ));
            movimentos.push(...resultados.filter(Boolean));
        }

        if (movimentos.length < 4 && falhas > 0) {
            throw new Error('A PokéAPI não carregou ataques suficientes. Recarregue a batalha para tentar novamente.');
        }
        if (movimentos.length === 0) {
            const response = await fetch('https://pokeapi.co/api/v2/move/tackle');
            if (!response.ok) throw new Error('Não foi possível carregar o ataque básico da PokéAPI.');
            const ataqueBasico = converterMovimento(await response.json());
            if (ataqueBasico === null) throw new Error('A PokéAPI retornou um ataque básico inválido.');
            return [ataqueBasico];
        }
        return movimentos.slice(0, 4);
    }

    renderizarAtaques() {
        this.movePanel.replaceChildren();
        if (this.movimentos.jogador.length === 0) {
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

    definirTamanhoSprite(id, altura) {
        const imagem = document.getElementById(id);
        const alturaEmDecimetros = Number(altura);
        if (!Number.isFinite(alturaEmDecimetros) || alturaEmDecimetros <= 0) {
            delete imagem.dataset.size;
            return;
        }

        imagem.dataset.size = alturaEmDecimetros <= 5
            ? 'small'
            : alturaEmDecimetros <= 14
                ? 'medium'
                : alturaEmDecimetros <= 29
                    ? 'large'
                    : 'very-large';
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
            `HP: ${combatente.hp} / ${combatente.hp_maximo} · ${Math.round(100 * percentualHp)}%`;
    }

    limparCombatentes() {
        ['jogador', 'oponente'].forEach((lado) => {
            document.getElementById(`${lado}Nome`).textContent = 'Aguardando...';
            document.getElementById(`${lado}Nivel`).textContent = '';
            document.getElementById(`${lado}HpBar`).value = 0;
            document.getElementById(`${lado}HpBar`).max = 1;
            document.getElementById(`${lado}HpTexto`).textContent = '—';
        });
        this.limparSprites();
    }

    definirBotoes(disabled) {
        document.querySelectorAll(
            '#iniciarBatalha, [data-acao], #fugirBatalha, .move-button, .switch-button'
        )
            .forEach((button) => { button.disabled = disabled; });
    }

    mostrarMensagem(texto) {
        this.message.textContent = texto;
        this.message.hidden = texto === '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.battleApp = new BattleApp('../src/controller/roteadorBatalha.php');
});
