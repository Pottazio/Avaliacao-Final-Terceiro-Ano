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

        this.form = document.getElementById('formBusca');
        this.campo = document.getElementById('campoPokemon');
        this.painel = document.getElementById('resultadoBusca');
        this.listaTime = document.getElementById('listaTime');
        this.contador = document.getElementById('contadorTime');
        this.mensagem = document.getElementById('mensagemTime');

        this.maximo = 6;
        this.time = [];

        if (this.form) this.form.addEventListener('submit', (e) => this.buscar(e));

        this.carregarTime();
    }

    /** Busca na PokeAPI e mostra o cartao de confirmação. */
    async buscar(evento) {
        evento.preventDefault();
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
