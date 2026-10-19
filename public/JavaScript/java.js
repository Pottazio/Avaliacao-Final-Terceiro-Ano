class MonstroBolso {

    constructor() {

        this.telas =
            document.querySelectorAll(".tela, .tela-batalha");

        this.botoes =
            document.querySelectorAll("[data-tela]");

        this.inicializar();
    }


    inicializar() {

        this.botoes.forEach(botao => {

            botao.addEventListener("click", () => {

                const tela = botao.dataset.tela;

                this.mostrarTela(tela);

            });

        });

        this.mostrarTela("inicio");
    }


    mostrarTela(nomeTela) {

        this.telas.forEach(tela => {

            tela.style.display = "none";

        });

        const telaSelecionada =
            document.getElementById(`tela-${nomeTela}`);

        if (telaSelecionada) {

            telaSelecionada.style.display = "block";

        }

    }

}


const app = new MonstroBolso();