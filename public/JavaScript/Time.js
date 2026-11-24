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
        this.moveCache = new Map();
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

    spriteUrl(id, shiny = false) {
        const diretorio = shiny ? 'shiny/' : '';
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${diretorio}${id}.png`;
    }

    async listarPrimeiraGeracao(quantidade) {
        const limite = Math.max(1, Math.min(151, Number(quantidade) || 1));
        const controlador = new AbortController();
        const timeout = setTimeout(() => controlador.abort(), 12000);
        let resposta;

        try {
            resposta = await fetch(
                `${this.base}/pokemon?limit=${limite}&offset=0`,
                { signal: controlador.signal }
            );
        } catch (erro) {
            if (erro.name === 'AbortError') {
                throw new Error('A PokeAPI demorou para responder. Verifique sua conexão e tente novamente.');
            }
            throw erro;
        } finally {
            clearTimeout(timeout);
        }

        if (!resposta.ok) {
            throw new Error('Não foi possível carregar a Pokédex.');
        }

        const dados = await resposta.json();
        return dados.results.map((pokemon, indice) => ({
            id: indice + 1,
            nome: pokemon.name,
            sprite: this.spriteUrl(indice + 1),
        }));
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

    async listarMovimentosDisponiveis(idPokemon, nivel) {
        const chaveCache = `${idPokemon}:${nivel}`;
        if (this.moveCache.has(chaveCache)) return this.moveCache.get(chaveCache);

        const respostaPokemon = await fetch(`${this.base}/pokemon/${idPokemon}`);
        if (!respostaPokemon.ok) {
            throw new Error('Não foi possível carregar os ataques deste Pokémon na PokéAPI.');
        }
        const dadosPokemon = await respostaPokemon.json();
        const referencias = (dadosPokemon.moves ?? [])
            .map((referencia) => {
                const niveis = (referencia.version_group_details ?? [])
                    .filter((detalhe) => (
                        detalhe.move_learn_method?.name === 'level-up'
                        && Number.isInteger(detalhe.level_learned_at)
                        && detalhe.level_learned_at <= nivel
                    ))
                    .map((detalhe) => detalhe.level_learned_at);
                return niveis.length > 0
                    ? { nome: referencia.move.name, url: referencia.move.url, nivel: Math.min(...niveis) }
                    : null;
            })
            .filter(Boolean)
            .sort((a, b) => a.nivel - b.nivel || a.nome.localeCompare(b.nome));

        const movimentos = [];
        for (let inicio = 0; inicio < referencias.length; inicio += 4) {
            const lote = await Promise.all(referencias.slice(inicio, inicio + 4).map(
                async (referencia) => {
                    const resposta = await fetch(referencia.url);
                    if (!resposta.ok) {
                        throw new Error(`Não foi possível carregar o ataque ${referencia.nome} da PokéAPI.`);
                    }
                    const dados = await resposta.json();
                    if (
                        !Number.isInteger(dados.power) || dados.power < 1 || dados.power > 250
                        || !['physical', 'special'].includes(dados.damage_class?.name)
                        || (dados.accuracy !== null && (!Number.isInteger(dados.accuracy)
                            || dados.accuracy < 1 || dados.accuracy > 100))
                        || !/^[a-z-]{1,20}$/.test(dados.type?.name || '')
                        || !Number.isInteger(dados.priority)
                        || dados.priority < -7 || dados.priority > 5
                    ) {
                        return null;
                    }
                    return {
                        nome: dados.name,
                        tipo: dados.type.name,
                        classe: dados.damage_class.name,
                        poder: dados.power,
                        precisao: Number.isInteger(dados.accuracy) ? dados.accuracy : null,
                        prioridade: dados.priority,
                        nivel: referencia.nivel,
                    };
                }
            ));
            movimentos.push(...lote.filter(Boolean));
        }

        this.moveCache.set(chaveCache, movimentos);
        return movimentos;
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
        this.painel = document.getElementById('resultadoBusca');
        this.listaTime = document.getElementById('listaTime');
        this.contador = document.getElementById('contadorTime');
        this.mensagem = document.getElementById('mensagemTime');
        this.galeriaPokemon = document.getElementById('galeriaPokemon');
        this.resumoProgresso = document.getElementById('resumoProgresso');
        this.modalMovePool = document.getElementById('modalMovePool');
        this.modalMoveTitulo = document.getElementById('modalMoveTitulo');
        this.modalMoveDescricao = document.getElementById('modalMoveDescricao');
        this.modalMoveLista = document.getElementById('modalMoveLista');
        this.modalMoveMensagem = document.getElementById('modalMoveMensagem');
        this.modalMoveContagem = document.getElementById('modalMoveContagem');
        this.campoNivelMovePool = document.getElementById('nivelPokemonEditado');
        this.campoShinyPokemon = document.getElementById('shinyPokemonEditado');
        this.botaoSalvarMovePool = document.getElementById('salvarMovePool');

        this.maximo = 6;
        this.nivelMaximo = Number(document.body.dataset.nivelMaximo || 5);
        this.time = [];
        this.pokemonEmEdicao = null;
        this.movimentosDisponiveis = [];
        this.movimentosSelecionados = new Set();
        this.movimentosCarregados = false;
        this.requisicaoMovePool = 0;

        if (this.form) this.form.addEventListener('submit', (e) => this.buscar(e));
        this.botaoSalvarMovePool.addEventListener('click', () => this.salvarMovePool());
        this.campoNivelMovePool.addEventListener('change', () => {
            this.carregarMovimentosDoNivel([...this.movimentosSelecionados]);
        });

        this.carregarTime();
        this.carregarGaleria();
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
            this.painel.innerHTML = '';
            this.painel.appendChild(this.criarCartao(pokemon));
        } catch (erro) {
            this.painel.innerHTML = '';
            this.mostrarMensagem(erro.message, 'erro');
        }
    }

    async carregarProgresso() {
        if (this.progresso) return this.progresso;

        const controlador = new AbortController();
        const timeout = setTimeout(() => controlador.abort(), 12000);
        let resposta;

        try {
            resposta = await fetch(`${this.urlRoteador}?acao=progresso`, {
                signal: controlador.signal,
            });
        } catch (erro) {
            if (erro.name === 'AbortError') {
                throw new Error('O servidor demorou para carregar o progresso. Atualize a página e tente novamente.');
            }
            throw erro;
        } finally {
            clearTimeout(timeout);
        }

        const json = await resposta.json();

        if (!resposta.ok || !json.sucesso) {
            throw new Error(json.mensagem || 'Não foi possível carregar o progresso.');
        }

        this.progresso = json.progresso;
        return this.progresso;
    }

    async carregarGaleria() {
        if (!this.galeriaPokemon) return;

        try {
            const progresso = await this.carregarProgresso();
            if (this.resumoProgresso) {
                this.resumoProgresso.textContent =
                    `Pokémon liberados: #001–#${String(progresso.pokedex_maximo).padStart(3, '0')} de 151 `
                    + `(${progresso.vitorias} vitórias). Selecione um para adicionar ao time.`;
            }

            this.galeriaPokemon.innerHTML = '';
            const carregando = document.createElement('p');
            carregando.className = 'time-vazio';
            carregando.textContent = 'Buscando imagens na PokéAPI...';
            this.galeriaPokemon.appendChild(carregando);

            const pokemons = await this.api.listarPrimeiraGeracao(progresso.pokedex_maximo);

            this.galeriaPokemon.innerHTML = '';
            pokemons.forEach((pokemon) => {
                this.galeriaPokemon.appendChild(this.criarCartaoGaleria(pokemon));
            });
        } catch (erro) {
            this.galeriaPokemon.innerHTML = '';
            const mensagem = document.createElement('p');
            mensagem.className = 'time-vazio';
            mensagem.textContent = erro.message || 'Não foi possível carregar os Pokémon liberados.';
            this.galeriaPokemon.appendChild(mensagem);
            this.mostrarMensagem(erro.message, 'erro');
            console.error(erro);
        }
    }

    criarCartaoGaleria(pokemon) {
        const cartao = document.createElement('button');
        cartao.type = 'button';
        cartao.className = 'cartao-pokedex';
        cartao.setAttribute(
            'aria-label',
            `Selecionar ${pokemon.nome}, número ${pokemon.id} da Pokédex`
        );

        const imagem = document.createElement('img');
        imagem.src = pokemon.sprite;
        imagem.alt = pokemon.nome;
        imagem.loading = 'lazy';
        cartao.appendChild(imagem);

        const numero = document.createElement('span');
        numero.className = 'pokedex-numero';
        numero.textContent = `#${String(pokemon.id).padStart(3, '0')}`;
        cartao.appendChild(numero);

        const nome = document.createElement('span');
        nome.className = 'pokedex-nome';
        nome.textContent = pokemon.nome;
        cartao.appendChild(nome);

        cartao.addEventListener('click', () => this.selecionarPokemon(pokemon.id));
        return cartao;
    }

    async selecionarPokemon(idPokedex) {
        this.campo.value = String(idPokedex);
        await this.buscar();
        this.painel.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
                await this.carregarTime();
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
            const texto = await resposta.text();
            let json;
            try {
                json = JSON.parse(texto);
            } catch {
                throw new Error(`O servidor respondeu ${resposta.status} ao carregar o time.`);
            }
            if (!resposta.ok || !json.sucesso) {
                throw new Error(json.mensagem || 'Não foi possível carregar o time.');
            }

            this.time = json.pokemons ?? [];
            this.maximo = json.maximo ?? 6;
            this.desenharTime();
        } catch (erro) {
            this.mostrarMensagem(erro.message || 'Erro ao carregar o time.', 'erro');
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
        slot.className = 'pokemon-card pokemon-time-card';

        const sprite = document.createElement('img');
        sprite.src = this.api.spriteUrl(Number(p.id_pokedex), Boolean(Number(p.shiny)));
        sprite.alt = p.especie || p.nome;
        sprite.width = 88;
        sprite.height = 88;
        sprite.loading = 'lazy';
        slot.appendChild(sprite);

        const nome = document.createElement('h3');
        nome.textContent = p.nome;
        slot.appendChild(nome);

        const tipos = document.createElement('p');
        tipos.className = 'pokemon-time-tipos';
        tipos.textContent = String(p.tipos || '—').replace(/,\s*/g, ', ').toUpperCase();
        slot.appendChild(tipos);

        if (Number(p.shiny) === 1 || p.shiny === true) {
            const shiny = document.createElement('span');
            shiny.className = 'pokemon-time-shiny';
            shiny.textContent = 'SHINY';
            slot.appendChild(shiny);
        }

        const nivel = document.createElement('p');
        nivel.className = 'pokemon-time-nivel';
        nivel.textContent = `Nível ${p.nivel}`;
        slot.appendChild(nivel);

        const ataques = document.createElement('p');
        ataques.className = 'pokemon-time-ataques';
        ataques.textContent = Array.isArray(p.move_pool) && p.move_pool.length > 0
            ? `Ataques: ${p.move_pool.map((nomeAtaque) => this.formatarNome(nomeAtaque)).join(', ')}`
            : 'Ataques: seleção automática';
        slot.appendChild(ataques);

        const atributos = document.createElement('div');
        atributos.className = 'pokemon-atributos pokemon-atributos-time';
        [
            [
                ['HP', p.HP],
                ['Ataque', p.ATQ],
                ['Defesa', p.DEF],
            ],
            [
                ['Velocidade', p.SPE],
                ['Ataque Especial', p.ATQE],
                ['Defesa Especial', p.DEFE],
            ],
        ].forEach((grupo) => {
            const coluna = document.createElement('div');
            coluna.className = 'pokemon-resumo-lado';
            grupo.forEach(([rotulo, valor]) => {
                const atributo = document.createElement('p');
                const nomeAtributo = document.createElement('strong');
                nomeAtributo.textContent = `${rotulo}: `;
                atributo.append(nomeAtributo, document.createTextNode(valor));
                coluna.appendChild(atributo);
            });
            atributos.appendChild(coluna);
        });
        slot.appendChild(atributos);

        const editarMovimentos = document.createElement('button');
        editarMovimentos.type = 'button';
        editarMovimentos.className = 'btn-editar-movimentos';
        editarMovimentos.textContent = 'Editar Pokémon';
        editarMovimentos.addEventListener('click', () => this.editarMovePool(p));
        slot.appendChild(editarMovimentos);

        const remover = document.createElement('button');
        remover.type = 'button';
        remover.className = 'btn-remover';
        remover.textContent = 'Remover';
        remover.addEventListener('click', () => this.remover(p.id_pokemon));
        slot.appendChild(remover);
        return slot;
    }

    async editarMovePool(pokemon) {
        this.pokemonEmEdicao = pokemon;
        this.movimentosDisponiveis = [];
        this.movimentosSelecionados = new Set(pokemon.move_pool || []);
        this.modalMoveTitulo.textContent = `Editar ${pokemon.nome}`;
        this.modalMoveDescricao.textContent =
            'Altere o nível e escolha até 4 ataques disponíveis. Sem seleção, os ataques serão escolhidos automaticamente.';
        this.campoNivelMovePool.replaceChildren();
        for (let nivel = 1; nivel <= this.nivelMaximo; nivel++) {
            const opcao = document.createElement('option');
            opcao.value = nivel;
            opcao.textContent = `Nível ${nivel}`;
            this.campoNivelMovePool.appendChild(opcao);
        }
        this.campoNivelMovePool.value = pokemon.nivel;
        this.campoShinyPokemon.checked = Number(pokemon.shiny) === 1 || pokemon.shiny === true;
        this.modalMovePool.showModal();
        await this.carregarMovimentosDoNivel([...this.movimentosSelecionados]);
    }

    async carregarMovimentosDoNivel(ataquesAnteriores) {
        const pokemon = this.pokemonEmEdicao;
        if (!pokemon) return;

        const requisicao = ++this.requisicaoMovePool;
        const nivel = Number(this.campoNivelMovePool.value);
        this.movimentosDisponiveis = [];
        this.movimentosCarregados = false;
        this.modalMoveLista.replaceChildren();
        this.modalMoveMensagem.textContent = 'Carregando ataques da PokéAPI...';
        this.modalMoveMensagem.dataset.tipo = 'info';
        this.botaoSalvarMovePool.disabled = true;
        this.atualizarContagemMovePool();

        try {
            const movimentos = await this.api.listarMovimentosDisponiveis(
                Number(pokemon.id_pokedex),
                nivel
            );
            if (requisicao !== this.requisicaoMovePool || this.pokemonEmEdicao !== pokemon) return;

            const disponiveis = new Set(movimentos.map((movimento) => movimento.nome));
            this.movimentosSelecionados = new Set(
                ataquesAnteriores.filter((nome) => disponiveis.has(nome))
            );
            this.movimentosDisponiveis = movimentos;
            this.movimentosCarregados = true;
            if (movimentos.length === 0) {
                this.modalMoveMensagem.textContent =
                    'Não há ataques de dano disponíveis neste nível. Deixe a seleção vazia para usar ataques automáticos.';
                this.modalMoveMensagem.dataset.tipo = 'info';
            } else if (this.movimentosSelecionados.size < ataquesAnteriores.length) {
                this.modalMoveMensagem.textContent =
                    'Alguns ataques não estão disponíveis neste nível e foram removidos da seleção.';
                this.modalMoveMensagem.dataset.tipo = 'info';
            } else {
                this.modalMoveMensagem.textContent = '';
            }
            this.renderizarOpcoesMovePool();
        } catch (erro) {
            if (requisicao !== this.requisicaoMovePool) return;
            this.modalMoveLista.replaceChildren();
            this.modalMoveMensagem.textContent =
                erro.message || 'Não foi possível carregar os ataques disponíveis.';
            this.modalMoveMensagem.dataset.tipo = 'erro';
            this.movimentosCarregados = false;
            this.atualizarContagemMovePool();
        }
    }

    renderizarOpcoesMovePool() {
        this.modalMoveLista.replaceChildren();
        this.movimentosDisponiveis.forEach((movimento) => {
            const opcao = document.createElement('label');
            opcao.className = 'move-pool-opcao';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = movimento.nome;
            checkbox.checked = this.movimentosSelecionados.has(movimento.nome);
            if (checkbox.checked) opcao.classList.add('move-pool-opcao-selecionada');
            checkbox.addEventListener('change', () => {
                if (checkbox.checked && this.movimentosSelecionados.size >= 4) {
                    checkbox.checked = false;
                    this.modalMoveMensagem.textContent = 'Cada Pokémon pode ter no máximo 4 ataques.';
                    this.modalMoveMensagem.dataset.tipo = 'erro';
                    return;
                }

                if (checkbox.checked) {
                    this.movimentosSelecionados.add(movimento.nome);
                } else {
                    this.movimentosSelecionados.delete(movimento.nome);
                }
                opcao.classList.toggle('move-pool-opcao-selecionada', checkbox.checked);
                this.modalMoveMensagem.textContent = '';
                this.atualizarContagemMovePool();
            });

            const texto = document.createElement('span');
            texto.className = 'move-pool-dados';
            const nome = document.createElement('strong');
            nome.textContent = this.formatarNome(movimento.nome);
            const detalhes = document.createElement('span');
            const precisao = movimento.precisao === null
                ? 'Não erra'
                : `${movimento.precisao}% precisão`;
            const classe = movimento.classe === 'physical' ? 'Físico' : 'Especial';
            const prioridade = movimento.prioridade === 0
                ? ''
                : ` · Prioridade ${movimento.prioridade > 0 ? '+' : ''}${movimento.prioridade}`;
            detalhes.textContent =
                `${movimento.tipo} · ${classe} · Poder ${movimento.poder} · ${precisao}${prioridade} · Nv. ${movimento.nivel}`;
            texto.append(nome, detalhes);
            opcao.append(checkbox, texto);
            this.modalMoveLista.appendChild(opcao);
        });
        this.atualizarContagemMovePool();
    }

    atualizarContagemMovePool() {
        const quantidade = this.movimentosSelecionados.size;
        this.modalMoveContagem.textContent = `${quantidade} / 4 ataques selecionados`;
        this.botaoSalvarMovePool.textContent = quantidade === 0
            ? 'Salvar com ataques aleatórios'
            : 'Salvar Pokémon';
        this.botaoSalvarMovePool.disabled =
            quantidade > 4 || !this.movimentosCarregados;
    }

    async salvarMovePool() {
        if (!this.pokemonEmEdicao || this.movimentosSelecionados.size > 4
            || !this.movimentosCarregados) return;

        this.botaoSalvarMovePool.disabled = true;
        this.modalMoveMensagem.textContent = 'Salvando Pokémon...';
        this.modalMoveMensagem.dataset.tipo = 'info';
        try {
            const resposta = await fetch(`${this.urlRoteador}?acao=salvarMovePool`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    id_pokemon: this.pokemonEmEdicao.id_pokemon,
                    nivel: this.campoNivelMovePool.value,
                    shiny: this.campoShinyPokemon.checked ? '1' : '0',
                    move_pool: JSON.stringify([...this.movimentosSelecionados]),
                }),
            });
            const texto = await resposta.text();
            let json;
            try {
                json = JSON.parse(texto);
            } catch {
                throw new Error(`O servidor respondeu ${resposta.status} ao salvar o Pokémon.`);
            }
            if (!resposta.ok || !json.sucesso) {
                throw new Error(json.mensagem || 'Não foi possível salvar o Pokémon.');
            }

            this.modalMovePool.close();
            await this.carregarTime();
            this.mostrarMensagem(json.mensagem, 'ok');
        } catch (erro) {
            this.modalMoveMensagem.textContent =
                erro.message || 'Não foi possível salvar o Pokémon.';
            this.modalMoveMensagem.dataset.tipo = 'erro';
            this.atualizarContagemMovePool();
        }
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
