/**
 * MONSTROBOLSO - Login e cadastro (lado cliente).
 * Caminho do projeto: public/JavaScript/login-form.js
 *
 * Orientação a Objetos em JavaScript (requisito do trabalho):
 * a classe LoginApp encapsula estado e comportamento e fala com o
 * backend por requisições assíncronas (fetch), recebendo JSON.
 */

class LoginApp {
    /** @param {string} urlController */
    constructor(urlController) {
        this.urlController = urlController;

        this.formLogin = document.getElementById('formLogin');
        this.formCadastro = document.getElementById('formCadastro');

        this.mensagem = document.getElementById('mensagemLogin')
            || document.getElementById('mensagemCadastro');

        if (this.formLogin) this.formLogin.addEventListener('submit', (e) => this.ligacao(e));
        if (this.formCadastro) this.formCadastro.addEventListener('submit', (e) => this.ligacao(e));
    }

    ligacao(evento) {
        evento.preventDefault();

        if (evento.target === this.formLogin) {
            this.enviar('entrar', {
                email: document.getElementById('loginUsuario').value.trim(),
                senha: document.getElementById('loginSenha').value,
            });
        } else if (evento.target === this.formCadastro) {
            this.enviar('cadastrar', {
                nome: document.getElementById('cadNome').value.trim(),
                email: document.getElementById('cadEmail').value.trim(),
                senha: document.getElementById('cadSenha').value,
            });
        }
    }

    /** Requisição assíncrona; o backend responde em JSON. */
    async enviar(acao, dados) {
        this.mostrarMensagem('Enviando...', 'info');
        this.bloquear(true);

        try {
            const resposta = await fetch(`${this.urlController}?acao=${acao}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams(dados),
            });

            const json = await resposta.json();

            if (json.sucesso) {
                this.mostrarMensagem(json.mensagem, 'ok');
                if (json.rota) window.location.href = json.rota;
                return;
            }

            this.mostrarMensagem(json.mensagem || 'Não foi possível entrar.', 'erro');
        } catch (erro) {
            this.mostrarMensagem('Falha de conexão com o servidor.', 'erro');
            console.error(erro);
        } finally {
            this.bloquear(false);
        }
    }

    bloquear(estado) {
        [this.formLogin, this.formCadastro].forEach((form) => {
            if (!form) return;
            form.querySelectorAll('button, input').forEach((el) => {
                el.disabled = estado;
            });
        });
    }

    mostrarMensagem(texto, tipo = 'erro') {
        if (!this.mensagem) return;
        this.mensagem.textContent = texto;
        this.mensagem.dataset.tipo = tipo;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.appLogin = new LoginApp('../src/controller/roteadorLogin.php');
});
