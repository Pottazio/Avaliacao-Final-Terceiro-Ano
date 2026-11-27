let csrfToken = '';

export async function requestShop(action, options = {}) {
    const response = await fetch(
        `../src/controller/roteadorLoja.php?acao=${encodeURIComponent(action)}`,
        {
            cache: 'no-store',
            ...options,
        }
    );
    const data = await response.json();

    if (!response.ok || !data.sucesso) {
        const error = new Error(data.mensagem || 'Falha na comunicação com a loja.');
        error.data = data;
        throw error;
    }

    if (data.csrfToken) csrfToken = data.csrfToken;
    return data;
}

export function shopRequestHeaders(json = false) {
    const headers = {};
    if (json) headers['Content-Type'] = 'application/json';
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    return headers;
}
