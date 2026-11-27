export class BattlePokemonService {
    constructor() {
        this.cacheMovimentos = new Map();
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
}
