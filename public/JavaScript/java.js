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
                `html/${pagina}.html`
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

        console.log(
            "Poké Mart carregado."
        );

    }

}


const jogo =
    new MonstroBolso();


function navegar(pagina) {

    jogo.navegar(pagina);

}


function comprarItem(item) {

    const mensagem =
        document.getElementById(
            "mensagemLoja"
        );


    if (!mensagem) {

        return;

    }


    mensagem.textContent =
        `Compra de ${item} será processada pelo backend.`;

}


document.addEventListener(
    "DOMContentLoaded",
    () => {

        jogo.navegar("index");

    }
);