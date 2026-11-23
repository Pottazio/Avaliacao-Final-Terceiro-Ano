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
    constructor(base = 'https://pokeapi.co/api/v2') {
        this.base = base;
        this.cache = new Map();          // evita repetir a mesma busca
    }

    spriteUrl(id) {
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${id}.png`;
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

        if (this.cache.has(chave)) return this.cache.get(chave);

        const resposta = await fetch(`${this.base}/pokemon/${encodeURIComponent(chave)}`);
        if (!resposta.ok) {
            throw new Error('Pokémon não encontrado na Pokédex.');
        }

        const bruto = await resposta.json();
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

        this.painel = document.getElementById('resultadoBusca');
        this.listaTime = document.getElementById('listaTime');
        this.contador = document.getElementById('contadorTime');
        this.mensagem = document.getElementById('mensagemTime');
        this.galeriaPokemon = document.getElementById('galeriaPokemon');
        this.resumoProgresso = document.getElementById('resumoProgresso');

        this.maximo = 6;
        this.time = [];
        this.progresso = null;

        this.carregarTime();
        this.carregarGaleria();
    }

    async mostrarPokemon(termo) {
        this.limparMensagem();
        this.painel.innerHTML = '';
        this.painel.appendChild(this.criarCarregando());

        try {
            const progresso = await this.carregarProgresso();
            const pokemon = await this.api.buscar(termo);

            if (pokemon.id_pokedex > progresso.pokedex_maximo) {
                throw new Error(
                    `Este Pokémon ainda está bloqueado. Sua Pokédex vai até #${String(progresso.pokedex_maximo).padStart(3, '0')}.`
                );
            }

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
        await this.mostrarPokemon(idPokedex);
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
        botao.addEventListener('click', () => this.adicionar(p, botao));
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
            ['HP', p.HP], ['ATQ', p.ATQ], ['ATQ.ESP', p.ATQE],
            ['DEF', p.DEF], ['DEF.ESP', p.DEFE], ['VEL', p.SPE],
        ];

        pares.forEach(([rotulo, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = rotulo;
            const dd = document.createElement('dd');
            dd.textContent = valor;
            tabela.append(dt, dd);
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
    async adicionar(pokemon, botao) {
        botao.disabled = true;
        this.mostrarMensagem('Salvando...', 'info');

        try {
            const dados = new URLSearchParams();
            dados.append('nome', pokemon.nome);
            dados.append('especie', pokemon.especie);
            dados.append('id_pokedex', pokemon.id_pokedex);
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

        if (p.id_pokedex) {
            const imagem = document.createElement('img');
            imagem.className = 'slot-sprite';
            imagem.src = this.api.spriteUrl(Number(p.id_pokedex));
            imagem.alt = p.nome;
            imagem.loading = 'lazy';
            slot.appendChild(imagem);
        }

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
        vel.textContent = `VEL ${p.SPE}`;
        slot.appendChild(vel);

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
