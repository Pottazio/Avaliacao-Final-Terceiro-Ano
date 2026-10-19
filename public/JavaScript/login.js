
// =========================
// LOGIN E CADASTRO
// =========================

const telaLogin = document.getElementById("tela-login");
const formLogin = document.getElementById("formLogin");
const formCadastro = document.getElementById("formCadastro");

const mostrarCadastro = document.getElementById("mostrarCadastro");
const voltarLogin = document.getElementById("voltarLogin");

const mensagemLogin = document.getElementById("mensagemLogin");

mostrarCadastro.addEventListener("click", () => {
    formLogin.style.display = "none";
    document.querySelector(".texto-cadastro").style.display = "none";

    formCadastro.style.display = "flex";
    mensagemLogin.textContent = "";
});

voltarLogin.addEventListener("click", () => {
    formCadastro.style.display = "none";
    formLogin.style.display = "flex";

    document.querySelector(".texto-cadastro").style.display = "block";
    mensagemLogin.textContent = "";
});

formLogin.addEventListener("submit", (evento) => {
    evento.preventDefault();

    const usuario = document.getElementById("loginUsuario").value.trim();
    const senha = document.getElementById("loginSenha").value;

    if (usuario && senha) {
        document.getElementById("nomeUsuario").textContent = usuario;

        telaLogin.style.display = "none";

        app.mostrarTela("inicio");
    }
});

formCadastro.addEventListener("submit", (evento) => {
    evento.preventDefault();

    mensagemLogin.textContent =
        "Cadastro visual funcionando! A integração com o PHP ainda será feita.";

    mensagemLogin.style.color = "#d62828";
});
