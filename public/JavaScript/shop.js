document.addEventListener('DOMContentLoaded', () => {
    const abas = Array.from(document.querySelectorAll('.loja-aba'));
    const paineis = Array.from(document.querySelectorAll('.loja-painel'));

    if (abas.length === 0 || paineis.length === 0) return;

    const ativarAba = (aba, atualizarUrl) => {
        const painelAtivo = aba.getAttribute('aria-controls');

        abas.forEach((item) => {
            const ativa = item === aba;
            item.setAttribute('aria-selected', String(ativa));
            item.tabIndex = ativa ? 0 : -1;
        });

        paineis.forEach((painel) => {
            painel.hidden = painel.id !== painelAtivo;
        });

        if (atualizarUrl) {
            window.history.replaceState(null, '', aba.href);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    abas.forEach((aba, indice) => {
        aba.tabIndex = aba.getAttribute('aria-selected') === 'true' ? 0 : -1;
        aba.addEventListener('click', (evento) => {
            evento.preventDefault();
            ativarAba(aba, true);
        });

        aba.addEventListener('keydown', (evento) => {
            if (evento.key !== 'ArrowLeft' && evento.key !== 'ArrowRight') return;

            evento.preventDefault();
            const proximoIndice = evento.key === 'ArrowRight'
                ? (indice + 1) % abas.length
                : (indice - 1 + abas.length) % abas.length;
            abas[proximoIndice].focus();
            ativarAba(abas[proximoIndice], true);
        });
    });
});
