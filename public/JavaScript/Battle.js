const iconesItensLog = {
    Potion: 'potionNV1.png',
    'Super Potion': 'SuperPotionNV2.png',
    'Hyper Potion': 'HyperPotionNV3.png',
    'Max Potion': 'MaxPotionNV4.png',
    'Full Restore': 'FullRestorePotionNV5.png',
    Antidote: 'AntidotePotion.png',
    'Burn Heal': 'BurnHealPotion.png',
    'Ice Heal': 'IceHealPotion.png',
    Awakening: 'AwakeningPotion.png',
    'Paralyze Heal': 'ParalyzeHealPotion.png',
    'Full Heal': 'FullHealPotion.png',
    Revive: 'RevivePotion.png',
    Aerodactylite: 'aerodactylite.png',
    Alakazite: 'alakazite.png',
    Beedrillite: 'beedrillite.png',
    Blastoisinite: 'blastoisinite.png',
    'Charizardite X': 'charizarditex.png',
    'Charizardite Y': 'charizarditey.png',
    Gengarite: 'gengarite.png',
    Gyaradosite: 'gyaradosite.png',
    Kangaskhanite: 'kangaskhanite.png',
    'Mewtwonite X': 'mewtwonitex.png',
    'Mewtwonite Y': 'mewtwonitey.png',
    Pidgeotite: 'pidgeotite.png',
    Pinsirite: 'pinsirite.png',
    Slowbronite: 'slowbronite.png',
    Venusaurite: 'venusaurite.png',
};

const pokemonMegaPorPedra = {
    aerodactylite: 142,
    alakazite: 65,
    beedrillite: 15,
    blastoisinite: 9,
    'charizardite x': 6,
    'charizardite y': 6,
    gengarite: 94,
    gyaradosite: 130,
    kangaskhanite: 115,
    'mewtwonite x': 150,
    'mewtwonite y': 150,
    pidgeotite: 18,
    pinsirite: 127,
    slowbronite: 80,
    venusaurite: 3,
};

class BattleApp {
    constructor(endpoint) {
        this.endpoint = endpoint;
        this.progress = null;
        this.battle = null;
        this.team = [];
        this.idsPokemonExcluidos = [];
        this.spriteIdsPokemon = new Map();
        this.spritesOponenteEmCarregamento = new Set();
        this.inventory = [];
        this.inventoryOpen = false;
        this.movimentos = { jogador: [], oponente: [] };
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
        document.getElementById('abrirMochila').addEventListener('click', () => {
            this.inventoryOpen = !this.inventoryOpen;
            this.renderizarBatalha();
        });
        document.getElementById('alvoItem').addEventListener('change', () => this.renderizarInventario());
        document.getElementById('selecaoTrocaOponente').addEventListener(
            'change',
            () => this.sincronizarAcoes()
        );
        document.getElementById('confirmarTrocaOponente').addEventListener(
            'click',
            () => this.decidirTrocaOponente('sim')
        );
        document.getElementById('manterPokemonOponente').addEventListener(
            'click',
            () => this.decidirTrocaOponente('nao')
        );
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
            this.idsPokemonExcluidos = result.ids_pokemon_excluidos_oponente || [];
            this.inventory = result.inventario || [];
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

        this.definirBotoes(true);
        this.mostrarMensagem('Preparando a equipe adversária...');
        try {
            const equipeOponente = await this.prepararEquipeOponente();
            await this.executar('iniciar', {
                id_pokemon: idPokemon,
                equipe_oponente: JSON.stringify(equipeOponente),
            });
        } catch (error) {
            this.mostrarMensagem(error.message || 'Não foi possível preparar a equipe adversária.');
            this.renderizarEquipe();
        }
    }

    async prepararEquipeOponente() {
        const quantidade = Number(this.progress?.pokemons_oponente);
        const minimo = Number(this.progress?.pokedex_minimo);
        const maximo = Number(this.progress?.pokedex_maximo);
        if (
            !Number.isInteger(quantidade) || quantidade < 1 || quantidade > 6
            || !Number.isInteger(minimo) || !Number.isInteger(maximo) || minimo > maximo
        ) {
            throw new Error('Os dados da área não permitem preparar a equipe adversária.');
        }

        const usados = new Set([
            ...this.idsPokemonExcluidos.map(Number),
            ...this.team.map((pokemon) => Number(pokemon.id_pokedex)),
        ]);
        const candidatos = [];
        for (let id = minimo; id <= maximo; id++) {
            if (!usados.has(id)) candidatos.push(id);
        }
        for (let indice = candidatos.length - 1; indice > 0; indice--) {
            const aleatorio = Math.floor(Math.random() * (indice + 1));
            [candidatos[indice], candidatos[aleatorio]] = [
                candidatos[aleatorio],
                candidatos[indice],
            ];
        }

        const ids = candidatos.slice(0, quantidade);
        if (ids.length !== quantidade) {
            throw new Error('Não há Pokémon suficientes disponíveis para esta equipe.');
        }

        return Promise.all(ids.map(async (id) => {
            const pokemon = await this.buscarPokemonApi(id);
            const atributos = Object.fromEntries(
                pokemon.stats.map((stat) => [stat.stat.name, stat.base_stat])
            );
            return {
                id_pokedex: pokemon.id,
                name: pokemon.name,
                types: pokemon.types.map((tipo) => tipo.type.name),
                hp: atributos.hp,
                attack: atributos.attack,
                'special-attack': atributos['special-attack'],
                defense: atributos.defense,
                'special-defense': atributos['special-defense'],
                speed: atributos.speed,
            };
        }));
    }

    async jogar(action, movimento = null) {
        const values = { acao_batalha: action };
        if (movimento) values.movimento = JSON.stringify(movimento);
        if (action === 'atacar' && movimento?.nome === 'sleep-talk') {
            values.movimentos_jogador = JSON.stringify(this.movimentos.jogador);
        }
        await this.executar('jogar', values);
    }

    async trocarPokemon(idPokemon) {
        await this.executar('jogar', {
            acao_batalha: 'trocar_pokemon',
            id_pokemon: idPokemon,
        });
    }

    async usarItem(idItem, idPokemon) {
        await this.executar('jogar', {
            acao_batalha: 'usar_item',
            id_item: idItem,
            id_pokemon: idPokemon,
        });
    }

    async decidirTrocaOponente(resposta) {
        const values = {
            acao_batalha: 'responder_troca',
            resposta_troca: resposta,
        };
        if (resposta === 'sim') {
            values.id_pokemon = document.getElementById('selecaoTrocaOponente').value;
        }
        await this.executar('jogar', values);
    }

    async fugir() {
        if (!window.confirm('Encerrar esta batalha? Ela contará como derrota.')) return;
        await this.executar('fugir');
    }

    async executar(action, values = {}) {
        if (action === 'jogar' && values.acao_batalha !== 'responder_troca') {
            if (this.movimentos.oponente.length === 0) {
                this.mostrarMensagem('Aguarde o carregamento dos ataques do oponente.');
                return;
            }
            values.movimentos_oponente = JSON.stringify(this.movimentos.oponente);
        }

        this.definirBotoes(true);
        try {
            await this.requisitar(action, values);
            await this.carregar();
        } catch (error) {
            this.mostrarMensagem(error.message);
            this.renderizarBatalha();
            if (action === 'iniciar') this.renderizarEquipe();
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
        const arena = document.querySelector('.arena-panel');
        arena.dataset.area = String(progress.area);
        arena.classList.toggle('arena-scenic', progress.area >= 0 && progress.area <= 6);
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
        const state = active ? this.battle.estado : null;
        const trocaPendente = Boolean(state?.troca_oponente_pendente);
        if (trocaPendente) this.inventoryOpen = false;
        const areaSetup = document.querySelector('.setup-panel .battle-setup');
        areaSetup.hidden = active || this.progress.concluido;
        document.querySelector('.setup-panel').hidden = active || this.progress.concluido;
        document.querySelector('.progression-panel').hidden = active;

        const controls = document.querySelectorAll('[data-acao], #fugirBatalha');
        controls.forEach((button) => {
            button.disabled = !active || (button.id !== 'fugirBatalha' && !this.podeJogarTurno());
        });
        document.getElementById('abrirMochila').disabled = !active || !this.podeJogarTurno();
        this.switchPanel.hidden = !active || trocaPendente;
        document.querySelector('.moves-panel').hidden = trocaPendente;
        document.getElementById('decisaoTrocaOponente').hidden = !trocaPendente;
        document.getElementById('painelInventario').hidden =
            !active || trocaPendente || !this.inventoryOpen;
        document.getElementById('abrirMochila').setAttribute('aria-expanded', String(
            active && this.inventoryOpen
        ));

        if (active) {
            this.renderizarCombatente('jogador', state.jogador);
            this.renderizarCombatente('oponente', state.oponente);
            this.renderizarOpcoesTroca(state);
            this.renderizarEquipeOponente(state);
            this.renderizarDecisaoTrocaOponente(state);
            this.renderizarInventario();
            document.getElementById('oponenteTreinador').textContent = this.battle.nome_oponente;
            this.carregarPokemonsBatalha(state.jogador, state.oponente);
        } else {
            this.inventoryOpen = false;
            this.switchList.replaceChildren();
            this.limparCombatentes();
            document.getElementById('oponenteTreinador').textContent = this.battle?.nome_oponente || '';
            this.batalhaMovimentosCarregada = '';
            this.movimentos = { jogador: [], oponente: [] };
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Inicie uma batalha para carregar os ataques.');
            this.renderizarInventario();
            this.renderizarEquipeOponente(null);
            this.renderizarDecisaoTrocaOponente(null);
        }

        document.querySelectorAll('.move-button').forEach((button) => {
            button.disabled = !active || !this.podeJogarTurno();
        });
        this.sincronizarAcoes();

        this.renderizarLogs();
    }

    renderizarLogs() {
        const logs = document.getElementById('logsBatalha');
        logs.replaceChildren();
        let temPreparacao = false;
        let temLogs = false;

        (this.battle?.logs || []).forEach((log) => {
            if (log.tipo === 'turn') {
                const titulo = document.createElement('li');
                titulo.className = 'log-turn';
                titulo.textContent = log.mensagem;
                logs.appendChild(titulo);
                return;
            }

            if (!temPreparacao && !temLogs) {
                const titulo = document.createElement('li');
                titulo.className = 'log-turn log-turn-setup';
                titulo.textContent = 'Início da batalha';
                logs.appendChild(titulo);
                temPreparacao = true;
            }

            const item = document.createElement('li');
            item.className = `log-${log.tipo} battle-log-entry`;

            const sprite = this.spriteDoAutorDoLog(log);
            if (sprite !== null) {
                const imagem = document.createElement('img');
                imagem.className = 'battle-log-icon';
                imagem.src = sprite;
                imagem.alt = log.tipo === 'item'
                    ? this.nomeItemNoLog(log.mensagem) || 'Item'
                    : 'Pokémon';
                imagem.width = 32;
                imagem.height = 32;
                item.appendChild(imagem);
            }

            const mensagem = document.createElement('span');
            mensagem.textContent = log.mensagem;
            item.appendChild(mensagem);
            logs.appendChild(item);
            temLogs = true;
        });

        if (!temLogs) {
            const vazio = document.createElement('li');
            vazio.textContent = 'Os acontecimentos da batalha aparecerão aqui.';
            logs.appendChild(vazio);
        }
        logs.scrollTop = logs.scrollHeight;
    }

    spriteDoAutorDoLog(log) {
        if (log.tipo === 'item') {
            const nomeItem = this.nomeItemNoLog(log.mensagem);
            return nomeItem ? `assets/itens/${iconesItensLog[nomeItem]}` : null;
        }

        const movimento = log.mensagem.match(/^(.+?) usou\b/);
        if (!movimento) return null;
        const nomeAutor = movimento[1];
        const pokemonDoTime = this.team.find((pokemon) => pokemon.nome === nomeAutor);
        if (pokemonDoTime) {
            return this.spriteUrl(
                Number(pokemonDoTime.id_pokedex),
                'front',
                Boolean(Number(pokemonDoTime.shiny))
            );
        }

        const oponente = this.battle?.estado?.oponente;
        if (oponente?.nome === nomeAutor) {
            const id = Number(oponente.id_pokedex);
            if (Number.isInteger(id) && id > 0) return this.spriteUrl(id, 'front');
        }

        const idEmCache = this.spriteIdsPokemon.get(nomeAutor);
        if (Number.isInteger(idEmCache) && idEmCache > 0) {
            return this.spriteUrl(idEmCache, 'front');
        }

        this.carregarSpriteOponenteDoLog(nomeAutor);
        return null;
    }

    nomeItemNoLog(mensagem) {
        const mensagemNormalizada = String(mensagem).toLowerCase();
        return Object.keys(iconesItensLog)
            .sort((nomeA, nomeB) => nomeB.length - nomeA.length)
            .find((nome) => mensagemNormalizada.includes(nome.toLowerCase())) || null;
    }

    carregarSpriteOponenteDoLog(nome) {
        if (this.spritesOponenteEmCarregamento.has(nome)) return;
        this.spritesOponenteEmCarregamento.add(nome);
        this.buscarPokemonApi(nome.toLowerCase().trim().replace(/\s+/g, '-'))
            .then((pokemon) => {
                this.spriteIdsPokemon.set(nome, pokemon.id);
                this.renderizarLogs();
            })
            .catch((error) => {
                this.mostrarMensagem(error.message || `Não foi possível carregar o sprite de ${nome}.`);
            })
            .finally(() => this.spritesOponenteEmCarregamento.delete(nome));
    }

    podeJogarTurno() {
        return !this.battle?.estado?.troca_oponente_pendente
            && this.movimentos.jogador.length > 0
            && this.movimentos.oponente.length > 0;
    }

    sincronizarAcoes() {
        const active = Boolean(this.battle?.em_andamento);
        const trocaPendente = Boolean(this.battle?.estado?.troca_oponente_pendente);
        const podeAgir = active && this.podeJogarTurno();
        document.querySelectorAll('[data-acao], .switch-button, .inventory-item, #abrirMochila')
            .forEach((button) => { button.disabled = !podeAgir; });
        document.querySelectorAll('.move-button')
            .forEach((button) => { button.disabled = !podeAgir; });
        const decisaoDisponivel = active && trocaPendente;
        const selectTroca = document.getElementById('selecaoTrocaOponente');
        document.getElementById('confirmarTrocaOponente').disabled =
            !decisaoDisponivel || !selectTroca.value;
        document.getElementById('manterPokemonOponente').disabled = !decisaoDisponivel;
        document.getElementById('fugirBatalha').disabled = !active;
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
            sprite.src = this.spriteUrl(
                Number(pokemon.id_pokedex),
                'front',
                Boolean(Number(pokemon.shiny))
            );
            sprite.alt = '';
            sprite.width = 64;
            sprite.height = 64;
            sprite.loading = 'lazy';
            button.appendChild(sprite);

            const details = document.createElement('span');
            details.className = 'switch-details';
            const name = document.createElement('span');
            name.className = 'switch-name';
            name.textContent = `${pokemon.nome}${reserva?.mega ? ' · Mega' : ''} · Nv. ${pokemon.nivel}`;
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

    renderizarEquipeOponente(state) {
        const painel = document.getElementById('equipeOponente');
        painel.replaceChildren();
        if (!state) return;

        const restantes = Number(state.pokemons_oponente_restantes || 0);
        painel.textContent = `Pokémon restantes: ${restantes}`;
    }

    renderizarDecisaoTrocaOponente(state) {
        const painel = document.getElementById('decisaoTrocaOponente');
        const select = document.getElementById('selecaoTrocaOponente');
        const texto = document.getElementById('decisaoTrocaTexto');
        select.replaceChildren();
        if (!state?.troca_oponente_pendente) {
            texto.textContent = '';
            return;
        }

        texto.textContent = `${this.battle.nome_oponente} irá usar um ${state.oponente.nome}. Deseja trocar seu Pokémon em campo?`;
        const disponiveis = (state.reservas_jogador || []).filter(
            (pokemon) => Number(pokemon.hp) > 0
        );
        if (disponiveis.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Não há Pokémon disponíveis para troca';
            select.appendChild(option);
            return;
        }

        disponiveis.forEach((pokemon) => {
            const option = document.createElement('option');
            option.value = pokemon.id_pokemon;
            option.textContent = `${pokemon.nome} · Nv. ${pokemon.nivel} · ${pokemon.hp}/${pokemon.hp_maximo} HP`;
            select.appendChild(option);
        });
    }

    renderizarInventario() {
        const lista = document.getElementById('itensInventario');
        const alvoSelect = document.getElementById('alvoItem');
        const alvoAnterior = alvoSelect.value;
        lista.replaceChildren();
        alvoSelect.replaceChildren();

        const state = this.battle?.estado;
        const alvos = state
            ? [state.jogador, ...(state.reservas_jogador || [])]
            : [];
        alvos.forEach((pokemon) => {
            const option = document.createElement('option');
            option.value = pokemon.id_pokemon;
            option.textContent = `${pokemon.nome}${pokemon.mega ? ' · Mega' : ''} · ${pokemon.hp}/${pokemon.hp_maximo} HP`;
            alvoSelect.appendChild(option);
        });
        if (alvos.some((pokemon) => String(pokemon.id_pokemon) === alvoAnterior)) {
            alvoSelect.value = alvoAnterior;
        }

        if (this.inventory.length === 0) {
            const texto = document.createElement('p');
            texto.textContent = 'Sua mochila está vazia. Compre itens no Poké Mart antes da batalha.';
            lista.appendChild(texto);
            return;
        }

        const alvo = alvos.find(
            (pokemon) => String(pokemon.id_pokemon) === alvoSelect.value
        );
        this.inventory.forEach((item) => {
            const revive = Number(item.revive) === 1;
            const curaStatus = Number(item.cura_status) === 1;
            const megaStone = Number(item.evolucao) === 1;
            const megaUsada = Boolean(state?.mega_utilizada);
            const idMega = pokemonMegaPorPedra[String(item.nome).toLowerCase()];
            const megaCompativel = Number(alvo?.id_pokedex) === idMega;
            const jaMega = Boolean(alvo?.mega);
            const hpAlvo = Number(alvo?.hp ?? 0);
            const hpMaximo = Number(alvo?.hp_maximo ?? 0);
            const podeUsar = megaStone
                ? Boolean(alvo) && hpAlvo > 0 && megaCompativel && !megaUsada && !jaMega
                : Boolean(alvo) && (revive
                ? hpAlvo <= 0
                : hpAlvo > 0 && (
                    hpAlvo < hpMaximo
                    || (curaStatus && String(alvo.status || 'NORMAL') !== 'NORMAL')
                ));
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'inventory-item';
            botao.disabled = !podeUsar || !this.podeJogarTurno();
            botao.title = !alvo
                ? 'Escolha um Pokémon para usar o item.'
                : megaStone && megaUsada
                    ? 'A Mega Evolução já foi usada nesta batalha.'
                    : megaStone && jaMega
                        ? 'Este Pokémon já Mega Evoluiu.'
                        : megaStone && !megaCompativel
                            ? `${item.nome} não pode ser usada neste Pokémon.`
                            : megaStone
                                ? 'Mega Evolui este Pokémon; a pedra não é consumida, mas o turno é gasto.'
                : revive && hpAlvo > 0
                    ? 'Revive só pode ser usado em um Pokémon desmaiado.'
                    : !revive && hpAlvo <= 0
                        ? 'Este Pokémon desmaiou; use um Revive.'
                        : hpAlvo >= hpMaximo && !curaStatus
                            ? 'Este Pokémon já está com o HP cheio.'
                            : `${item.descricao || item.nome} (consome seu turno).`;
            botao.addEventListener('click', () => {
                this.usarItem(item.id_item, alvoSelect.value);
            });

            const nome = document.createElement('strong');
            nome.textContent = `${item.nome} · ${item.quantidade}x`;
            const descricao = document.createElement('span');
            descricao.textContent = item.descricao || 'Item de batalha';
            botao.append(nome, descricao);
            lista.appendChild(botao);
        });
    }

    async carregarPokemonsBatalha(jogador, oponente) {
        const identificadorJogador = this.identificarPokemon(jogador, 'jogador');
        const identificadorOponente = this.identificarPokemon(oponente, 'oponente');
        const formaMegaJogador = jogador.forma_mega || '';
        const chave = `${this.battle.id}:${identificadorJogador}:${formaMegaJogador}:${identificadorOponente}`;
        if (this.batalhaMovimentosCarregada === chave) return;
        this.batalhaMovimentosCarregada = chave;
        this.movimentos = { jogador: [], oponente: [] };
        this.movePanel.replaceChildren();
        this.adicionarTextoAtaques('Carregando sprites e ataques da PokéAPI...');

        const pokemonDoTime = this.team.find(
            (pokemon) => Number(pokemon.id_pokemon) === Number(jogador.id_pokemon)
        );
        const jogadorShiny = Boolean(Number(jogador.shiny ?? pokemonDoTime?.shiny));
        if (Number.isInteger(Number(identificadorJogador))) {
            this.definirSprite(
                'jogadorSprite',
                [
                    this.spriteUrl(identificadorJogador, 'back', jogadorShiny),
                    this.spriteUrl(identificadorJogador, 'back'),
                    this.spriteUrl(identificadorJogador, 'front', jogadorShiny),
                    this.spriteUrl(identificadorJogador, 'front'),
                ],
                jogador.nome
            );
        }

        try {
            const [dadosJogador, dadosOponente] = await Promise.all([
                this.buscarPokemonApi(identificadorJogador),
                this.buscarPokemonApi(identificadorOponente),
            ]);
            if (this.batalhaMovimentosCarregada !== chave) return;
            let dadosFormaJogador = dadosJogador;
            if (formaMegaJogador) {
                try {
                    dadosFormaJogador = await this.buscarPokemonApi(formaMegaJogador);
                } catch (error) {
                    console.error(error);
                    this.mostrarMensagem('Não foi possível carregar o sprite Mega; a batalha continuará normalmente.');
                }
            }
            if (this.batalhaMovimentosCarregada !== chave) return;
            this.spriteIdsPokemon.set(oponente.nome, dadosOponente.id);

            this.definirSprite(
                'jogadorSprite',
                this.candidatosSprite(dadosFormaJogador, 'back', jogadorShiny),
                jogador.mega_nome || jogador.nome
            );
            this.definirSprite(
                'oponenteSprite',
                this.candidatosSprite(dadosOponente, 'front', Boolean(Number(oponente.shiny))),
                oponente.nome
            );

            const nivelMaximoGolpes = this.progress?.area === 0
                ? 20
                : Number(jogador.nivel);
            const movePool = Array.isArray(pokemonDoTime?.move_pool)
                ? pokemonDoTime.move_pool
                : [];
            if (movePool.length > 0) {
                const referenciasPorNome = new Map(
                    (dadosJogador.moves || []).map((referencia) => [
                        referencia.move.name,
                        referencia,
                    ])
                );
                const referenciasSelecionadas = movePool.map(
                    (nome) => referenciasPorNome.get(nome)
                );
                if (referenciasSelecionadas.some((referencia) => !referencia)) {
                    throw new Error('Um ataque salvo não está mais disponível para este Pokémon na PokéAPI.');
                }
                this.movimentos.jogador = await this.buscarMovimentos(
                    referenciasSelecionadas,
                    nivelMaximoGolpes,
                    false
                );
                if (this.movimentos.jogador.length !== movePool.length) {
                    throw new Error('Não foi possível carregar todos os ataques salvos deste Pokémon.');
                }
            } else {
                this.movimentos.jogador = await this.buscarMovimentos(
                    dadosJogador.moves,
                    nivelMaximoGolpes
                );
            }
            const nivelMaximoGolpesOponente = this.progress?.area === 0
                ? 20
                : Number(oponente.nivel);
            this.movimentos.oponente = await this.buscarMovimentos(
                dadosOponente.moves,
                nivelMaximoGolpesOponente
            );
            if (this.batalhaMovimentosCarregada !== chave) return;
            this.renderizarAtaques();
            this.renderizarInventario();
            this.renderizarLogs();
            this.sincronizarAcoes();
        } catch (error) {
            if (this.batalhaMovimentosCarregada !== chave) return;
            this.movePanel.replaceChildren();
            this.adicionarTextoAtaques('Não foi possível carregar os ataques da PokéAPI. Verifique a conexão e recarregue a página.');
            this.mostrarMensagem(error.message || 'Falha ao carregar os dados da batalha.');
            this.movimentos = { jogador: [], oponente: [] };
            this.sincronizarAcoes();
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

    async buscarMovimentos(referencias, nivelMaximo, randomizar = true) {
        const candidatos = [...(referencias || [])].filter((item) => {
            if (!item?.move?.url || !item?.move?.name || !Array.isArray(item.version_group_details)) {
                return false;
            }

            return item.version_group_details.some((detalhe) => {
                const metodo = detalhe.move_learn_method?.name;
                if (['snore', 'sleep-talk'].includes(item.move.name)) {
                    return ['level-up', 'machine', 'tutor', 'egg'].includes(metodo);
                }
                return metodo === 'level-up'
                    && Number.isInteger(detalhe.level_learned_at)
                    && detalhe.level_learned_at <= nivelMaximo;
            });
        });
        if (randomizar) {
            for (let indice = candidatos.length - 1; indice > 0; indice--) {
                const aleatorio = Math.floor(Math.random() * (indice + 1));
                [candidatos[indice], candidatos[aleatorio]] = [
                    candidatos[aleatorio],
                    candidatos[indice],
                ];
            }
        }

        const converterMovimento = (movimento) => {
            const classe = movimento.damage_class?.name;
            const alvo = movimento.target?.name;
            const movimentoSleepTalk = movimento.name === 'sleep-talk' && classe === 'status';
            const ailment = {
                burn: 'burn',
                freeze: 'freeze',
                paralysis: 'paralysis',
                poison: 'poison',
                toxic: 'poison',
                sleep: 'sleep',
            }[movimento.meta?.ailment?.name];
            const podeAtingirOponente = movimentoSleepTalk || (alvo !== 'user'
                && alvo !== 'user-or-ally'
                && alvo !== 'users-field'
                && alvo !== 'user-side');
            const movimentoDeDano = ['physical', 'special'].includes(classe)
                && Number.isInteger(movimento.power)
                && movimento.power >= 1 && movimento.power <= 250;
            const movimentoDeStatus = classe === 'status' && (Boolean(ailment) || movimentoSleepTalk);
            if (
                (!movimentoDeDano && !movimentoDeStatus)
                || !podeAtingirOponente
                || !Number.isInteger(movimento.priority)
                || movimento.priority < -7 || movimento.priority > 5
                || (movimento.accuracy !== null && (!Number.isInteger(movimento.accuracy)
                    || movimento.accuracy < 1 || movimento.accuracy > 100))
                || !/^[a-z-]{1,20}$/.test(movimento.type?.name || '')
            ) return null;

            const chanceMeta = Number(movimento.meta?.ailment_chance);
            const chanceEfeito = Number(movimento.effect_chance);
            const chanceStatus = movimentoSleepTalk
                ? 0
                : movimentoDeStatus
                ? (chanceMeta > 0 ? chanceMeta : chanceEfeito > 0 ? chanceEfeito : 100)
                : chanceMeta > 0 ? chanceMeta : chanceEfeito > 0 ? chanceEfeito : 0;
            if (
                !Number.isInteger(chanceStatus)
                || chanceStatus < 0 || chanceStatus > 100
                || (movimentoDeStatus && !movimentoSleepTalk && chanceStatus < 1)
            ) return null;

            if (
                !/^[a-z0-9-]{1,60}$/.test(movimento.name || '')
            ) return null;
            return {
                nome: movimento.name,
                tipo: movimento.type.name,
                classe,
                poder: movimentoSleepTalk || movimentoDeStatus
                    ? 0
                    : movimento.name === 'snore' ? 40 : movimento.power,
                precisao: Number.isInteger(movimento.accuracy) ? movimento.accuracy : null,
                prioridade: movimento.priority,
                efeito_status: ailment && chanceStatus > 0 ? ailment : null,
                chance_status: ailment && chanceStatus > 0 ? chanceStatus : 0,
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
            this.adicionarTextoAtaques('Não há movimentos válidos disponíveis para este Pokémon na PokéAPI.');
            return;
        }

        this.movimentos.jogador.forEach((movimento) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'move-button';
            button.disabled = !this.battle?.em_andamento;
            const prioridade = Number.isInteger(movimento.prioridade) ? movimento.prioridade : 0;
            const textoPrioridade = prioridade === 0
                ? ''
                : `, prioridade ${prioridade > 0 ? '+' : ''}${prioridade}`;
            const descricaoMovimento = movimento.classe === 'status'
                ? movimento.nome === 'sleep-talk'
                    ? ', chama outro movimento enquanto dorme'
                    : `, golpe de status que pode causar ${this.nomeStatus(movimento.efeito_status)}`
                : `, poder ${movimento.poder}`;
            button.setAttribute(
                'aria-label',
                `${this.formatarNomeAtaque(movimento.nome)}${descricaoMovimento}${textoPrioridade}`
            );

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
            const classeLegivel = movimento.classe === 'status' ? 'Status' : classe;
            const precisao = movimento.precisao === null ? 'Não erra' : `${movimento.precisao}% precisão`;
            const resumo = [classeLegivel, precisao];
            if (movimento.efeito_status) {
                resumo.push(`${this.nomeStatus(movimento.efeito_status)} · ${movimento.chance_status}%`);
            }
            if (prioridade !== 0) {
                resumo.push(`Prioridade ${prioridade > 0 ? '+' : ''}${prioridade}`);
            }
            detalhes.textContent = resumo.join(' · ');
            if (movimento.classe === 'status') {
                poder.textContent = movimento.nome === 'sleep-talk'
                    ? 'Usa outro movimento enquanto dorme'
                    : 'Golpe de status';
            }
            meta.append(poder, document.createElement('br'), tipo, detalhes);
            button.append(nome, meta);
            button.addEventListener('click', () => this.jogar('atacar', movimento));
            this.movePanel.appendChild(button);
        });
    }

    formatarNomeAtaque(nome) {
        return nome.replace(/-/g, ' ');
    }

    nomeStatus(status) {
        return {
            burn: 'Queimadura',
            freeze: 'Congelamento',
            paralysis: 'Paralisia',
            poison: 'Envenenamento',
            sleep: 'Sono',
        }[status] || status;
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

    candidatosSprite(pokemon, lado, shiny) {
        const sprites = pokemon.sprites || {};
        const showdown = sprites.other?.showdown || {};
        const artwork = sprites.other?.['official-artwork'] || {};
        const ladoOposto = lado === 'back' ? 'front' : 'back';
        const variantes = shiny ? ['shiny', 'default'] : ['default'];
        const candidatos = [];

        variantes.forEach((variante) => {
            candidatos.push(showdown[`${lado}_${variante}`], sprites[`${lado}_${variante}`]);
            if (lado === 'back') {
                candidatos.push(showdown[`front_${variante}`], sprites[`front_${variante}`]);
            } else {
                candidatos.push(artwork[`front_${variante}`]);
            }
        });

        candidatos.push(
            this.spriteUrl(pokemon.id, lado, shiny),
            this.spriteUrl(pokemon.id, ladoOposto, shiny)
        );
        if (shiny) {
            candidatos.push(
                this.spriteUrl(pokemon.id, lado, false),
                this.spriteUrl(pokemon.id, ladoOposto, false)
            );
        }
        return candidatos;
    }

    spriteUrl(id, lado, shiny = false) {
        const diretorio = shiny
            ? (lado === 'back' ? 'back/shiny/' : 'shiny/')
            : (lado === 'back' ? 'back/' : '');
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${diretorio}${Number(id)}.png`;
    }

    limparSprites() {
        ['jogadorSprite', 'oponenteSprite'].forEach((id) => this.definirSprite(id, '', ''));
    }

    renderizarCombatente(lado, combatente) {
        const nome = document.getElementById(`${lado}Nome`);
        nome.textContent = combatente.mega
            ? `${combatente.nome} · Mega`
            : combatente.nome;
        nome.classList.add('capitalize');
        nome.classList.toggle('mega-evoluido', Boolean(combatente.mega));
        document.getElementById(`${lado}Nivel`).textContent = `Nível ${combatente.nivel}`;
        const status = String(combatente.status || 'NORMAL');
        document.getElementById(`${lado}Status`).textContent = status === 'NORMAL'
            ? ''
            : ({
                BURN: 'Queimado',
                FREEZE: 'Congelado',
                PARALYSIS: 'Paralisado',
                POISON: 'Envenenado',
                SLEEP: 'Dormindo',
            }[status] || status);
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
            document.getElementById(`${lado}Status`).textContent = '';
            document.getElementById(`${lado}HpBar`).value = 0;
            document.getElementById(`${lado}HpBar`).max = 1;
            document.getElementById(`${lado}HpTexto`).textContent = '—';
        });
        this.limparSprites();
    }

    definirBotoes(disabled) {
        document.querySelectorAll(
            '#iniciarBatalha, [data-acao], #fugirBatalha, #abrirMochila, .move-button, .switch-button, .inventory-item, #confirmarTrocaOponente, #manterPokemonOponente'
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
