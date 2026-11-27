/** Cliente da PokeAPI. */
export class PokeApiClient {
    static MAX_PRIMEIRA_GERACAO = 151;

    constructor(base = 'https://pokeapi.co/api/v2') {
        this.base = base;
        this.cache = new Map();          // evita repetir a mesma busca
        this.moveCache = new Map();
        this.evolutionStageCache = new Map();
        this.evolutionChainCache = new Map();
        this.listaCache = null;
    }

    spriteUrl(id, shiny = false) {
        const diretorio = shiny ? 'shiny/' : '';
        return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${diretorio}${id}.png`;
    }

    async listarPrimeiraGeracao(quantidade) {
        const carregarListaCompleta = quantidade === undefined;
        if (carregarListaCompleta && this.listaCache) return this.listaCache;

        const limite = carregarListaCompleta
            ? PokeApiClient.MAX_PRIMEIRA_GERACAO
            : Math.max(1, Math.min(PokeApiClient.MAX_PRIMEIRA_GERACAO, Number(quantidade) || 1));
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
        const pokemons = (dados.results ?? []).map((pokemon, indice) => ({
            id: Number(pokemon.url.match(/\/pokemon\/(\d+)\/?$/)?.[1]) || indice + 1,
            nome: pokemon.name,
            sprite: this.spriteUrl(Number(pokemon.url.match(/\/pokemon\/(\d+)\/?$/)?.[1]) || indice + 1),
        }));

        if (carregarListaCompleta) {
            if (pokemons.length !== PokeApiClient.MAX_PRIMEIRA_GERACAO) {
                throw new Error('A Pokédex retornou uma lista incompleta da primeira geração.');
            }
            this.listaCache = pokemons;
        }

        return pokemons;
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

    async obterEstagioPrimeiraGeracao(idPokemon) {
        const id = Number(idPokemon);
        if (!Number.isInteger(id) || id < 1 || id > PokeApiClient.MAX_PRIMEIRA_GERACAO) {
            throw new Error('Só estão disponíveis Pokémon da primeira geração (#001–#151).');
        }

        if (!this.evolutionStageCache.has(id)) {
            const requisicao = this.carregarEstagioPrimeiraGeracao(id);
            this.evolutionStageCache.set(id, requisicao);
            requisicao.catch(() => this.evolutionStageCache.delete(id));
        }

        return this.evolutionStageCache.get(id);
    }

    async carregarEstagioPrimeiraGeracao(idPokemon) {
        const respostaEspecie = await fetch(`${this.base}/pokemon-species/${idPokemon}`);
        if (!respostaEspecie.ok) {
            throw new Error('Não foi possível carregar a evolução deste Pokémon na PokéAPI.');
        }
        const especie = await respostaEspecie.json();
        const urlCadeia = especie.evolution_chain?.url;
        if (typeof urlCadeia !== 'string') {
            throw new Error('A PokéAPI não informou a cadeia evolutiva deste Pokémon.');
        }

        if (!this.evolutionChainCache.has(urlCadeia)) {
            const requisicao = fetch(urlCadeia).then((resposta) => {
                if (!resposta.ok) {
                    throw new Error('Não foi possível carregar a cadeia evolutiva na PokéAPI.');
                }
                return resposta.json();
            });
            this.evolutionChainCache.set(urlCadeia, requisicao);
            requisicao.catch(() => this.evolutionChainCache.delete(urlCadeia));
        }

        const cadeia = await this.evolutionChainCache.get(urlCadeia);
        const estagios = new Map();
        const percorrer = (no, proximoEstagio) => {
            const idEspecie = Number(no.species?.url?.match(/\/pokemon-species\/(\d+)\/?$/)?.[1]);
            const pertencePrimeiraGeracao =
                Number.isInteger(idEspecie) && idEspecie >= 1
                && idEspecie <= PokeApiClient.MAX_PRIMEIRA_GERACAO;

            if (pertencePrimeiraGeracao) {
                estagios.set(idEspecie, proximoEstagio);
            }

            const estagioDosFilhos = proximoEstagio + (pertencePrimeiraGeracao ? 1 : 0);
            (no.evolves_to ?? []).forEach((filho) => percorrer(filho, estagioDosFilhos));
        };
        percorrer(cadeia.chain, 0);

        const estagio = estagios.get(idPokemon);
        if (!Number.isInteger(estagio)) {
            throw new Error('Não foi possível identificar o estágio evolutivo deste Pokémon.');
        }
        return estagio;
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
