class MonstroBolso {

    constructor() {

        this.paginaAtual = "index";

        this.time = [];

    }


    async navegar(pagina) {

        const paginas = [
            "index",
            "team",
            "battle",
            "shop"
        ];


        if (!paginas.includes(pagina)) {

            pagina = "index";

        }


        try {

            const resposta = await fetch(
                `html/${pagina}.html?v=20261118`
            );


            if (!resposta.ok) {

                throw new Error(
                    "Não foi possível carregar a página."
                );

            }


            const html = await resposta.text();


            document.getElementById(
                "conteudo"
            ).innerHTML = html;


            this.paginaAtual = pagina;


            this.configurarPagina();

        }

        catch (erro) {

            document.getElementById(
                "conteudo"
            ).innerHTML = `

                <section class="pagina">

                    <h2>Erro</h2>

                    <p>
                        Não foi possível carregar
                        esta página.
                    </p>

                </section>

            `;

            console.error(erro);

        }

    }


    configurarPagina() {

        switch (this.paginaAtual) {

            case "team":

                this.configurarTeam();

                break;


            case "battle":

                this.configurarBatalha();

                break;


            case "shop":

                this.configurarLoja();

                break;

        }

    }


    configurarTeam() {

        const form =
            document.getElementById(
                "pokemonForm"
            );


        if (!form) {

            return;

        }


        form.addEventListener(
            "submit",
            (evento) => {

                evento.preventDefault();

                this.buscarPokemon();

            }
        );

    }


    async buscarPokemon() {

        const campo =
            document.getElementById(
                "pokemonNome"
            );


        const nome =
            campo.value
                .trim()
                .toLowerCase();


        if (!nome) {

            return;

        }


        const resultado =
            document.getElementById(
                "pokemonResultado"
            );


        resultado.innerHTML =
            "<p>Pesquisando...</p>";


        try {

            const resposta =
                await fetch(
                    `https://pokeapi.co/api/v2/pokemon/${nome}`
                );


            if (!resposta.ok) {

                throw new Error(
                    "Pokémon não encontrado."
                );

            }


            const pokemon =
                await resposta.json();


            resultado.innerHTML = `

                <div class="pokemon-card">

                    <img
                        src="${pokemon.sprites.front_default}"
                        alt="${pokemon.name}"
                    >

                    <h3>
                        ${pokemon.name}
                    </h3>

                    <p>
                        HP:
                        ${pokemon.stats[0].base_stat}
                    </p>

                    <p>
                        Velocidade:
                        ${pokemon.stats[5].base_stat}
                    </p>

                    <button
                        id="adicionarPokemon"
                    >
                        Adicionar ao time
                    </button>

                </div>

            `;


            document
                .getElementById(
                    "adicionarPokemon"
                )
                .addEventListener(
                    "click",
                    () => {

                        this.adicionarPokemon(
                            pokemon
                        );

                    }
                );

        }

        catch (erro) {

            resultado.innerHTML = `

                <p>
                    ${erro.message}
                </p>

            `;

        }

    }


    adicionarPokemon(pokemon) {

        if (this.time.length >= 6) {

            alert(
                "Seu time já possui 6 Pokémon."
            );

            return;

        }


        const existente =
            this.time.find(
                p => p.id === pokemon.id
            );


        if (existente) {

            alert(
                "Esse Pokémon já está no time."
            );

            return;

        }


        this.time.push(pokemon);

        this.mostrarTime();

    }


    mostrarTime() {

        const area =
            document.getElementById(
                "timePokemon"
            );


        if (!area) {

            return;

        }


        area.innerHTML =
            this.time
                .map(
                    (pokemon, indice) => `

                    <div class="pokemon-card">

                        <img
                            src="${pokemon.sprites.front_default}"
                            alt="${pokemon.name}"
                        >

                        <h3>
                            ${pokemon.name}
                        </h3>

                        <p>
                            Posição:
                            ${indice + 1}
                        </p>

                    </div>

                `
                )
                .join("");

    }


    configurarBatalha() {

        const botao =
            document.getElementById(
                "iniciarBatalha"
            );


        if (!botao) {

            return;

        }


        botao.addEventListener(
            "click",
            () => {

                if (this.time.length === 0) {

                    document.getElementById(
                        "resultadoBatalha"
                    ).innerHTML = `

                        <p>
                            Monte um time antes
                            de iniciar uma batalha.
                        </p>

                    `;

                    return;

                }


                document.getElementById(
                    "resultadoBatalha"
                ).innerHTML = `

                    <p>
                        Batalha iniciada!
                    </p>

                `;


                document.getElementById(
                    "atacar"
                ).disabled = false;

            }
        );

    }


    configurarLoja() {
        this.carregarItensLoja();
    }


    async carregarItensLoja() {

        const lista =
            document.getElementById(
                "listaItensLoja"
            );


        if (!lista) {

            return;

        }


        const nomes = [
            "potion",
            "super-potion",
            "hyper-potion",
            "max-potion",
            "full-restore",
            "antidote",
            "burn-heal",
            "ice-heal",
            "awakening",
            "paralyze-heal",
            "full-heal",
            "revive"
        ];


        try {

            let dadosLoja =
                await requisicaoLoja("listar");
            this.csrfToken = dadosLoja.csrfToken;


            try {

                const itensApi =
                    await Promise.all(
                    nomes.map(
                        async (slug) => {

                            try {

                                const resposta =
                                    await fetch(
                                        `https://pokeapi.co/api/v2/item/${slug}`
                                    );


                                if (!resposta.ok) {

                                    throw new Error(
                                        `Falha ao buscar ${slug} na PokeAPI.`
                                    );

                                }


                                const item =
                                    await resposta.json();


                                const efeito =
                                    item.effect_entries.find(
                                        (entrada) =>
                                            entrada.language.name === "en"
                                    );


                                return {
                                    slug,
                                    descricao: efeito
                                        ? efeito.short_effect
                                        : "Item curativo."
                                };

                            }

                            catch (erro) {

                                console.error(erro);
                                return null;

                            }

                        }
                    )
                );


                const itensValidos =
                    itensApi.filter(
                        (item) => item !== null
                    );


                if (itensValidos.length > 0) {

                    await requisicaoLoja(
                        "sincronizar",
                        {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-Token": dadosLoja.csrfToken
                            },
                            body: JSON.stringify({
                                itens: itensValidos
                            })
                        }
                    );

                }

                const mensagem =
                    document.getElementById(
                        "mensagemLoja"
                    );

                if (mensagem && itensValidos.length < nomes.length) {

                    mensagem.textContent =
                        itensValidos.length === 0
                            ? "PokeAPI indisponível. Exibindo os itens já salvos no banco."
                            : "A PokeAPI retornou apenas parte dos itens; o catálogo foi sincronizado parcialmente.";
                }


                dadosLoja =
                    await requisicaoLoja("listar");
                this.csrfToken = dadosLoja.csrfToken;

            }


            catch (erroApi) {

                console.error(erroApi);

                const mensagem =
                    document.getElementById(
                        "mensagemLoja"
                    );


                if (mensagem) {

                    mensagem.textContent =
                        "Não foi possível atualizar os itens pela PokeAPI. Exibindo os itens já salvos no banco.";

                }

            }


            if (dadosLoja.itens.length === 0) {

                throw new Error(
                    "A loja ainda não tem itens salvos. Verifique a conexão com a PokeAPI e tente novamente."
                );

            }


            const saldo =
                document.getElementById(
                    "saldoPokemon"
                );


            if (saldo) {

                saldo.textContent =
                    dadosLoja.saldo;

            }


            const saldoHeader =
                document.getElementById(
                    "saldoHeader"
                );


            if (saldoHeader) {

                saldoHeader.textContent =
                    dadosLoja.saldo;

            }


            lista.innerHTML =
                dadosLoja.itens.map(
                    (item) => {

                        const nomeSeguro =
                            escaparHtml(item.nome);
                        const descricaoSegura =
                            escaparHtml(item.descricao || "Item curativo.");
                        return `
                            <article class="produto">
                                <h3>${nomeSeguro}</h3>
                                <p>${descricaoSegura}</p>
                                <p class="precoItem">
                                    <strong>${item.preco} Pokédollars</strong>
                                </p>
                                <p>
                                    No inventário:
                                    <span id="quantidade-item-${item.id_item}">${item.quantidade}</span>
                                </p>
                                <button
                                    onclick="comprarItem(${Number(item.id_item)})"
                                >
                                    Comprar
                                </button>
                            </article>
                        `;

                    }
                ).join("");

        }


        catch (erro) {

            lista.innerHTML =
                `<p>${escaparHtml(erro.message || "Não foi possível carregar a loja.")}</p>`;

            console.error(erro);

        }

    }

}


const jogo =
    new MonstroBolso();


function navegar(pagina) {

    jogo.navegar(pagina);

}


async function comprarItem(idItem) {

    const mensagem =
        document.getElementById(
            "mensagemLoja"
        );


    if (!mensagem) {

        return;

    }


    mensagem.textContent =
        "Processando compra...";


    try {

        const resultado =
            await requisicaoLoja(
                "comprar",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": jogo.csrfToken
                    },
                    body: JSON.stringify({
                        id_item: idItem,
                        quantidade: 1
                    })
                }
            );


        const saldo =
            document.getElementById(
                "saldoPokemon"
            );


        if (saldo) {

            saldo.textContent =
                resultado.saldo;

        }


        const saldoHeader =
            document.getElementById(
                "saldoHeader"
            );


        if (saldoHeader) {

            saldoHeader.textContent =
                resultado.saldo;

        }


        const quantidade =
            document.getElementById(
                `quantidade-item-${resultado.id_item}`
            );


        if (quantidade) {

            quantidade.textContent =
                resultado.quantidade;

        }


        mensagem.textContent =
            resultado.mensagem;

    }


    catch (erro) {

        if (erro.dados && erro.dados.saldo !== undefined) {

            const saldo =
                document.getElementById(
                    "saldoPokemon"
                );


            if (saldo) {

                saldo.textContent =
                    erro.dados.saldo;

            }


            const saldoHeader =
                document.getElementById(
                    "saldoHeader"
                );


            if (saldoHeader) {

                saldoHeader.textContent =
                    erro.dados.saldo;

            }

        }


        mensagem.textContent =
            erro.message;

    }

}


async function requisicaoLoja(acao, opcoes = {}) {

    const resposta =
        await fetch(
            `../src/controller/roteadorLoja.php?acao=${encodeURIComponent(acao)}`,
            {
                cache: "no-store",
                ...opcoes
            }
        );


    const dados =
        await resposta.json();


    if (!resposta.ok || !dados.sucesso) {

        const erro =
            new Error(
                dados.mensagem || "Falha na comunicação com a loja."
            );


        erro.dados = dados;

        throw erro;

    }


    return dados;

}


function escaparHtml(valor) {

    return String(valor)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

}


document.addEventListener(
    "DOMContentLoaded",
    () => {

        jogo.navegar("index");

    }
);