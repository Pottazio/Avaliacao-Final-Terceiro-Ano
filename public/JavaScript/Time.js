/**
 * POKENOAS - Team Builder (lado cliente).
 * Caminho do projeto: public/JavaScript/Time.js
 *
 * CONSUMO DE API: fala com a PokeAPI (https://pokeapi.co) para trazer
 * os atributos do Pokemon e converte para o formato da tabela `pokedex`.
 *
 * ORIENTACAO A OBJETOS EM JAVASCRIPT (requisito do trabalho):
 *  - Classe PokeApiClient: encapsula o acesso a API externa
 *  - Classe TimeApp: encapsula o estado e o comportamento da tela
 */

/** Cliente da PokeAPI. */
class PokeApiClient {
    static MAX_PRIMEIRA_GERACAO = 151;

    constructor(base = 'https://pokeapi.co/api/v2') {
        this.base = base;
        this.cache = new Map();          // evita repetir a mesma busca
        this.listaCache = null;
    }

    async listarPrimeiraGeracao() {
        if (this.listaCache) return this.listaCache;

        const resposta = await fetch(`${this.base}/pokemon?limit=${PokeApiClient.MAX_PRIMEIRA_GERACAO}`);
        if (!resposta.ok) throw new Error('Não foi possível carregar a lista da Pokédex.');

        const dados = await resposta.json();
        this.listaCache = (dados.results ?? [])
            .map((pokemon) => {
                const id = Number(pokemon.url.match(/\/pokemon\/(\d+)\/?$/)?.[1]);
                return { id, nome: pokemon.name };
            })
            .filter((pokemon) => Number.isInteger(pokemon.id) && pokemon.id >= 1 && pokemon.id <= PokeApiClient.MAX_PRIMEIRA_GERACAO);

        if (this.listaCache.length !== PokeApiClient.MAX_PRIMEIRA_GERACAO) {
            throw new Error('A Pokédex retornou uma lista incompleta da primeira geração.');
        }

        return this.listaCache;
    }

    /** Busca um Pokemon por nome ou numero e devolve dados normalizados. */
    async buscar(termo) {
        const chave = String(termo).trim().toLowerCase();
        if (chave === '') throw new Error('Informe um nome ou número.');
        if (/^\d+$/.test(chave) && (Number(chave) < 1 || Number(chave) > PokeApiClient.MAX_PRIMEIRA_GERACAO)) {
            throw new Error('Só estão disponíveis Pokémon da primeira geração (#001–#151).');
        }

        if (this.cache.has(chave)) return this.cache.get(chave);

        const resposta = await fetch(`${this.base}/pokemon/${encodeURIComponent(chave)}`);
        if (!resposta.ok) {
            throw new Error('Pokémon não encontrado na Pokédex.');
        }

        const bruto = await resposta.json();
        if (bruto.id < 1 || bruto.id > PokeApiClient.MAX_PRIMEIRA_GERACAO) {
            throw new Error('Só estão disponíveis Pokémon da primeira geração (#001–#151).');
        }
        const normalizado = this.normalizar(bruto);

        this.cache.set(chave, normalizado);
        return normalizado;
    }

    /**
     * Converte a resposta da API no formato das colunas de `pokedex`:
     * id_pokedex, name, HP, ATQ, ATQE, DEF, DEFE, SPE
     * e os tipos em pokemon_tipos / tipos.
     *
     * A PokeAPI devolve `stats` como lista. Cada item tem `base_stat` e
     * `stat.name` ('hp', 'attack', 'special-attack', 'defense',
     * 'special-defense', 'speed').
     */
    normalizar(bruto) {
        if (!Number.isInteger(bruto.id) || bruto.id < 1 || bruto.id > PokeApiClient.MAX_PRIMEIRA_GERACAO) {
            throw new Error('Só estão disponíveis Pokémon da primeira geração (#001–#151).');
        }

        const stats = {};
        for (const item of bruto.stats ?? []) {
            stats[item.stat.name] = item.base_stat;
        }

        return {
            id_pokedex: bruto.id,
            nome: bruto.name,
            especie: bruto.name,
            HP: stats['hp'] ?? 0,
            ATQ: stats['attack'] ?? 0,
            ATQE: stats['special-attack'] ?? 0,
            DEF: stats['defense'] ?? 0,
            DEFE: stats['special-defense'] ?? 0,
            SPE: stats['speed'] ?? 0,
            tipos: (bruto.types ?? []).map((t) => t.type.name),
            sprite: bruto.sprites?.front_default ?? '',
        };
    }
}

/** Tela do Team Builder. */
class TimeApp {
    /**
     * @param {string} urlRoteador caminho do roteador PHP
     * @param {PokeApiClient} api
     */
    constructor(urlRoteador, api) {
        this.urlRoteador = urlRoteador;
        this.api = api;

        this.form = document.getElementById('formBusca');
        this.campo = document.getElementById('campoPokemon');
        this.sugestoes = document.getElementById('sugestoesPokemon');
        this.painel = document.getElementById('resultadoBusca');
        this.listaTime = document.getElementById('listaTime');
        this.contador = document.getElementById('contadorTime');
        this.mensagem = document.getElementById('mensagemTime');

        this.maximo = 6;
        this.nivelMaximo = Number(document.body.dataset.nivelMaximo || 5);
        this.time = [];
        this.listaPokemon = [];
        this.indiceSugestao = -1;

        if (this.form) this.form.addEventListener('submit', (e) => this.buscar(e));
        if (this.campo) {
            this.campo.addEventListener('input', () => this.atualizarSugestoes());
            this.campo.addEventListener('keydown', (e) => this.navegarSugestoes(e));
        }
        if (this.sugestoes) {
            this.sugestoes.addEventListener('click', (e) => {
                const opcao = e.target.closest('[data-pokemon]');
                if (opcao) this.selecionarPokemon(opcao.dataset.pokemon);
            });
        }

        this.carregarPokemonsDisponiveis();
        this.carregarTime();
    }

    async carregarPokemonsDisponiveis() {
        try {
            this.listaPokemon = await this.api.listarPrimeiraGeracao();
        } catch (erro) {
            this.mostrarMensagem(erro.message, 'erro');
        }
    }

    atualizarSugestoes() {
        const termo = this.campo.value.trim().toLowerCase();
        this.indiceSugestao = -1;
        if (termo.length === 0 || !this.listaPokemon.length) {
            this.sugestoes.replaceChildren();
            this.sugestoes.hidden = true;
            this.campo.setAttribute('aria-expanded', 'false');
            this.campo.removeAttribute('aria-activedescendant');
            return;
        }

        const correspondencias = this.listaPokemon
            .filter((pokemon) => pokemon.nome.startsWith(termo))
            .slice(0, 8);
        this.sugestoes.replaceChildren();
        correspondencias.forEach((pokemon, indice) => {
            const opcao = document.createElement('button');
            opcao.type = 'button';
            opcao.id = `sugestao-pokemon-${indice}`;
            opcao.className = 'sugestao-pokemon';
            opcao.dataset.pokemon = pokemon.nome;
            opcao.setAttribute('role', 'option');
            opcao.setAttribute('aria-selected', 'false');

            const sprite = document.createElement('img');
            sprite.src = this.spriteUrl(pokemon.id);
            sprite.alt = '';
            sprite.width = 40;
            sprite.height = 40;
            sprite.loading = 'lazy';

            const nome = document.createElement('span');
            nome.textContent = `${this.formatarNome(pokemon.nome)} #${String(pokemon.id).padStart(3, '0')}`;
            opcao.append(sprite, nome);
            this.sugestoes.appendChild(opcao);
        });
        this.sugestoes.hidden = correspondencias.length === 0;
        this.campo.setAttribute('aria-expanded', String(correspondencias.length > 0));
    }

    navegarSugestoes(evento) {
        const opcoes = [...this.sugestoes.querySelectorAll('[role="option"]')];
        if (evento.key === 'Escape') {
            this.sugestoes.hidden = true;
            this.campo.setAttribute('aria-expanded', 'false');
            this.campo.removeAttribute('aria-activedescendant');
            return;
        }
        if (!opcoes.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(evento.key)) return;

        if (evento.key === 'Enter' && this.indiceSugestao >= 0) {
            evento.preventDefault();
            this.selecionarPokemon(opcoes[this.indiceSugestao].dataset.pokemon);
            return;
        }
        if (evento.key === 'Enter') return;

        evento.preventDefault();
        const delta = evento.key === 'ArrowDown' ? 1 : -1;
        this.indiceSugestao = (this.indiceSugestao + delta + opcoes.length) % opcoes.length;
        opcoes.forEach((opcao, indice) => {
            const ativo = indice === this.indiceSugestao;
            opcao.setAttribute('aria-selected', String(ativo));
        });
        this.campo.setAttribute('aria-activedescendant', opcoes[this.indiceSugestao].id);
    }

    selecionarPokemon(nome) {
        this.campo.value = nome;
        this.sugestoes.hidden = true;
        this.campo.setAttribute('aria-expanded', 'false');
        this.campo.removeAttribute('aria-activedescendant');
        this.buscar();
    }

    spriteUrl(id) {
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${id}.png`;
    }

    formatarNome(nome) {
        return nome.replace(/-/g, ' ').replace(/\b\w/g, (letra) => letra.toUpperCase());
    }

    /** Busca na PokeAPI e mostra o cartao de confirmação. */
    async buscar(evento) {
        evento?.preventDefault();
        this.limparMensagem();
        this.painel.innerHTML = '';
        this.painel.appendChild(this.criarCarregando());

        try {
            const pokemon = await this.api.buscar(this.campo.value);
            if (pokemon.id_pokedex < 1 || pokemon.id_pokedex > PokeApiClient.MAX_PRIMEIRA_GERACAO) {
                throw new Error('Só estão disponíveis Pokémon da primeira geração (#001–#151).');
            }
            this.painel.innerHTML = '';
            this.painel.appendChild(this.criarCartao(pokemon));
        } catch (erro) {
            this.painel.innerHTML = '';
            this.mostrarMensagem(erro.message, 'erro');
        }
    }

    /** Cartao do Pokemon encontrado, com botao de adicionar. */
    criarCartao(p) {
        const moldura = document.createElement('div');
        moldura.className = 'minha-carta';

        if (p.sprite) {
            const img = document.createElement('img');
            img.src = p.sprite;
            img.alt = p.nome;
            img.className = 'carta-sprite';
            moldura.appendChild(img);
        }

        const titulo = document.createElement('h3');
        titulo.className = 'carta-nome';
        titulo.textContent = `${p.nome} #${p.id_pokedex}`;
        moldura.appendChild(titulo);

        moldura.appendChild(this.criarTipos(p.tipos));
        moldura.appendChild(this.criarAtributos(p));

        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn';
        botao.textContent = 'Adicionar ao time';
        botao.disabled = this.time.length >= this.maximo;
        const labelNivel = document.createElement('label');
        labelNivel.textContent = 'Nível do Pokémon';
        const nivel = document.createElement('select');
        nivel.className = 'pokemon-nivel';
        for (let valor = 1; valor <= this.nivelMaximo; valor++) {
            const option = document.createElement('option');
            option.value = valor;
            option.textContent = `Nível ${valor}`;
            nivel.appendChild(option);
        }
        nivel.value = this.nivelMaximo;
        labelNivel.appendChild(nivel);
        moldura.appendChild(labelNivel);
        botao.addEventListener('click', () => this.adicionar(p, botao, nivel.value));
        moldura.appendChild(botao);

        return moldura;
    }

    criarTipos(tipos) {
        const caixa = document.createElement('div');
        caixa.className = 'carta-tipos';

        (tipos ?? []).forEach((tipo) => {
            const etiqueta = document.createElement('span');
            etiqueta.className = `tipo tipo-${tipo}`;
            etiqueta.textContent = tipo;
            caixa.appendChild(etiqueta);
        });

        return caixa;
    }

    criarAtributos(p) {
        const tabela = document.createElement('dl');
        tabela.className = 'carta-atributos';

        const pares = [
            ['HP', p.HP], ['Ataque', p.ATQ], ['Defesa', p.DEF],
            ['Ataque esp.', p.ATQE], ['Defesa esp.', p.DEFE], ['Velocidade', p.SPE],
        ];

        pares.forEach(([rotulo, valor]) => {
            const item = document.createElement('div');
            item.className = 'atributo-pokemon';
            const cabecalho = document.createElement('div');
            cabecalho.className = 'atributo-cabecalho';
            const dt = document.createElement('dt');
            dt.textContent = rotulo;
            const dd = document.createElement('dd');
            dd.textContent = valor;
            cabecalho.append(dt, dd);
            const barra = document.createElement('progress');
            barra.max = 255;
            barra.value = Math.min(255, Number(valor));
            barra.setAttribute('aria-label', `${rotulo}: ${valor}`);
            item.append(cabecalho, barra);
            tabela.appendChild(item);
        });

        return tabela;
    }

    criarCarregando() {
        const p = document.createElement('p');
        p.className = 'carregando';
        p.textContent = 'Consultando a Pokédex...';
        return p;
    }

    /** Envia o Pokemon para o servidor gravar. */
    async adicionar(pokemon, botao, nivel) {
        botao.disabled = true;
        this.mostrarMensagem('Salvando...', 'info');

        try {
            const dados = new URLSearchParams();
            dados.append('nome', pokemon.nome);
            dados.append('especie', pokemon.especie);
            dados.append('id_pokedex', pokemon.id_pokedex);
            dados.append('nivel', nivel);
            ['HP', 'ATQ', 'ATQE', 'DEF', 'DEFE', 'SPE'].forEach((k) => {
                dados.append(k, pokemon[k]);
            });
            (pokemon.tipos ?? []).forEach((t) => dados.append('tipos[]', t));

            const resposta = await fetch(`${this.urlRoteador}?acao=adicionar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: dados,
            });

            const texto = await resposta.text();

            let json;
            try {
                json = JSON.parse(texto);
            } catch (e) {
                const trecho = texto.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
                this.mostrarMensagem(`Servidor respondeu ${resposta.status}: ${trecho}`, 'erro');
                botao.disabled = false;
                return;
            }

            if (json.sucesso) {
                this.mostrarMensagem(json.mensagem, 'ok');
                this.carregarTime();
            } else {
                this.mostrarMensagem(json.mensagem, 'erro');
                botao.disabled = false;
            }
        } catch (erro) {
            this.mostrarMensagem('Não foi possível falar com o servidor.', 'erro');
            botao.disabled = false;
            console.error(erro);
        }
    }

    /** Recarrega o time via JSON e redesenha a lista. */
    async carregarTime() {
        if (!this.listaTime) return;

        try {
            const resposta = await fetch(`${this.urlRoteador}?acao=listar`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await resposta.json();

            this.time = json.pokemons ?? [];
            this.maximo = json.maximo ?? 6;
            this.desenharTime();
        } catch (erro) {
            this.mostrarMensagem('Erro ao carregar o time.', 'erro');
            console.error(erro);
        }
    }

    desenharTime() {
        this.listaTime.innerHTML = '';

        if (this.contador) {
            this.contador.textContent = `${this.time.length} / ${this.maximo}`;
        }

        if (this.time.length === 0) {
            const vazio = document.createElement('p');
            vazio.className = 'time-vazio';
            vazio.textContent = 'Nenhum Pokémon no time ainda.';
            this.listaTime.appendChild(vazio);
            return;
        }

        this.time.forEach((p) => this.listaTime.appendChild(this.criarSlot(p)));
    }

    criarSlot(p) {
        const slot = document.createElement('div');
        slot.className = 'slot-pokemon';

        const sprite = document.createElement('img');
        sprite.className = 'slot-sprite';
        sprite.src = this.spriteUrl(Number(p.id_pokedex));
        sprite.alt = `Sprite de ${p.nome}`;
        sprite.width = 96;
        sprite.height = 96;
        sprite.loading = 'lazy';
        slot.appendChild(sprite);

        const nome = document.createElement('p');
        nome.className = 'slot-nome';
        nome.textContent = p.nome;
        slot.appendChild(nome);

        const tipos = document.createElement('p');
        tipos.className = 'slot-tipos';
        tipos.textContent = p.tipos ?? '—';
        slot.appendChild(tipos);

        const vel = document.createElement('p');
        vel.className = 'slot-vel';
        vel.textContent = `Nível ${p.nivel}`;
        slot.appendChild(vel);

        slot.appendChild(this.criarAtributos(p));

        const nivelLabel = document.createElement('label');
        nivelLabel.className = 'slot-nivel-label';
        nivelLabel.textContent = 'Alterar nível';
        const nivelSelect = document.createElement('select');
        nivelSelect.className = 'pokemon-nivel';
        for (let valor = 1; valor <= this.nivelMaximo; valor++) {
            const option = document.createElement('option');
            option.value = valor;
            option.textContent = `Nível ${valor}`;
            option.selected = Number(p.nivel) === valor;
            nivelSelect.appendChild(option);
        }
        nivelLabel.appendChild(nivelSelect);
        slot.appendChild(nivelLabel);

        const salvarNivel = document.createElement('button');
        salvarNivel.type = 'button';
        salvarNivel.className = 'btn-remover';
        salvarNivel.textContent = 'Salvar nível';
        salvarNivel.addEventListener('click', () => this.atualizarNivel(p.id_pokemon, nivelSelect.value));
        slot.appendChild(salvarNivel);

        const remover = document.createElement('button');
        remover.type = 'button';
        remover.className = 'btn-remover';
        remover.textContent = 'Remover';
        remover.addEventListener('click', () => this.remover(p.id_pokemon));
        slot.appendChild(remover);

        return slot;
    }

    async remover(idPokemon) {
        this.mostrarMensagem('Removendo...', 'info');

        try {
            const resposta = await fetch(`${this.urlRoteador}?acao=remover`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ id_pokemon: idPokemon }),
            });
            const json = await resposta.json();

            this.mostrarMensagem(json.mensagem, json.sucesso ? 'ok' : 'erro');
            this.carregarTime();
        } catch (erro) {
            this.mostrarMensagem('Não foi possível remover.', 'erro');
            console.error(erro);
        }
    }

    async atualizarNivel(idPokemon, nivel) {
        this.mostrarMensagem('Atualizando nível...', 'info');
        try {
            const resposta = await fetch(`${this.urlRoteador}?acao=atualizarNivel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ id_pokemon: idPokemon, nivel }),
            });
            const json = await resposta.json();
            this.mostrarMensagem(json.mensagem, json.sucesso ? 'ok' : 'erro');
            if (json.sucesso) this.carregarTime();
        } catch (erro) {
            this.mostrarMensagem('Não foi possível atualizar o nível.', 'erro');
            console.error(erro);
        }
    }

    mostrarMensagem(texto, tipo) {
        if (!this.mensagem) return;
        this.mensagem.textContent = texto;
        this.mensagem.dataset.tipo = tipo ?? 'erro';
    }

    limparMensagem() {
        if (this.mensagem) this.mensagem.textContent = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.appTime = new TimeApp(
        '../src/controller/roteadorTime.php',
        new PokeApiClient()
    );
});
