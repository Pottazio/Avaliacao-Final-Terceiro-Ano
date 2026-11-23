/**
 * MONSTROBOLSO - Login e cadastro (lado cliente).
 * Caminho do projeto: public/JavaScript/Login.js
 *
 * Orientação a Objetos em JavaScript (requisito do trabalho):
 * a classe LoginApp encapsula estado e comportamento e fala com o
 * backend por requisições assíncronas (fetch), recebendo JSON.
 */

class LoginApp {
    /** @param {string} urlController */
    constructor(urlController) {
        this.urlController = urlController;

        this.telaLogin = document.getElementById('telaLogin');
        this.telaCadastro = document.getElementById('telaCadastro');

        this.formLogin = document.getElementById('formLogin');
        this.formCadastro = document.getElementById('formCadastro');

        this.mensagemLogin = document.getElementById('mensagemLogin');
        this.mensagemCadastro = document.getElementById('mensagemCadastro');
        this.linkCadastro = document.getElementById('mostrarCadastro');
        this.linkVoltar = document.getElementById('voltarLogin');

        // Toda instância nasce sem handlers: registra só o que existe.
        if (this.formLogin) this.formLogin.addEventListener('submit', (e) => this.ligacao(e));
        if (this.formCadastro) this.formCadastro.addEventListener('submit', (e) => this.ligacao(e));
        if (this.linkCadastro) this.linkCadastro.addEventListener('click', (e) => this.trocarAba(e, 'cadastro'));
        if (this.linkVoltar) this.linkVoltar.addEventListener('click', (e) => this.trocarAba(e, 'login'));
    }

    /** Um único ponto de escuta para os dois formulários. */
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

    trocarAba(evento, aba) {
        evento.preventDefault();
        this.limparMensagens();

        const paraCadastro = aba === 'cadastro';
        this.telaLogin.style.display = paraCadastro ? 'none' : 'block';
        this.telaCadastro.style.display = paraCadastro ? 'block' : 'none';
    }

    /** Requisição assíncrona; o backend responde em JSON. */
    async enviar(acao, dados) {
        this.mostrarMensagem(acao, 'Enviando...', 'info');
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

            const corpo = await resposta.text();
            let json;
            try {
                json = JSON.parse(corpo);
            } catch {
                throw new Error(`O servidor respondeu HTTP ${resposta.status} sem JSON válido. Confira o log do PHP.`);
            }

            if (resposta.ok && json.sucesso) {
                this.mostrarMensagem(
                    acao,
                    acao === 'cadastrar' ? 'Conta criada! Redirecionando para o login...' : 'Entrando...',
                    'ok'
                );
                window.location.href = json.rota
                    || (acao === 'cadastrar' ? '../login/login.php' : '../public/index.php');
                return;
            }

            this.mostrarMensagem(
                acao,
                json.mensagem || 'Não foi possível concluir a operação.',
                'erro'
            );
        } catch (erro) {
            this.mostrarMensagem(
                acao,
                erro instanceof TypeError ? 'Falha de conexão com o servidor.' : erro.message,
                'erro'
            );
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

    mostrarMensagem(acao, texto, tipo = 'erro') {
        const mensagem = acao === 'cadastrar' ? this.mensagemCadastro : this.mensagemLogin;
        if (!mensagem) return;
        mensagem.textContent = texto;
        mensagem.dataset.tipo = tipo;
    }

    limparMensagens() {
        [this.mensagemLogin, this.mensagemCadastro].forEach((mensagem) => {
            if (!mensagem) return;
            mensagem.textContent = '';
            delete mensagem.dataset.tipo;
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Caminho relativo ao login/login.php (é de lá que a página é aberta).
    window.appLogin = new LoginApp('../src/controller/roteadorLogin.php');
});
