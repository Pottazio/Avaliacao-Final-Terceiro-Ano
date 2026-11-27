import { requestShop, shopRequestHeaders } from './shop-api.js';

export function setupShopPurchases() {
    const message = document.getElementById('mensagemLoja');
    if (!message) return;

    document.getElementById('listaItensLoja')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-comprar-item]');
        if (!button || button.disabled) return;

        button.disabled = true;
        message.textContent = 'Processando compra...';
        try {
            const result = await requestShop('comprar', {
                method: 'POST',
                headers: shopRequestHeaders(true),
                body: JSON.stringify({
                    id_item: Number(button.dataset.comprarItem),
                    quantidade: 1,
                }),
            });

            updateBalances(result.saldo);
            const quantity = document.getElementById(`quantidade-item-${result.id_item}`);
            if (quantity) quantity.textContent = result.quantidade;
            disableOwnedMegaStones(result.ids_pokemon_com_pedra_mega || []);
            message.textContent = result.mensagem;
        } catch (error) {
            if (error.data?.saldo !== undefined) updateBalances(error.data.saldo);
            message.textContent = error.message;
        } finally {
            if (!button.closest('.produto')?.classList.contains('produto-bloqueado')) {
                button.disabled = false;
            }
        }
    });
}

function updateBalances(balance) {
    ['saldoPokemon', 'saldoHeader'].forEach((id) => {
        const element = document.getElementById(id);
        if (element && balance !== undefined) element.textContent = balance;
    });
}

function disableOwnedMegaStones(ids) {
    const ownedIds = new Set(ids.map(Number));
    document.querySelectorAll('.produto[data-evolucao="1"]').forEach((product) => {
        if (!ownedIds.has(Number(product.dataset.pokemonMega))) return;
        product.classList.add('produto-bloqueado');
        const button = product.querySelector('button');
        if (button) {
            button.disabled = true;
            button.textContent = 'Já possui';
        }
    });
}
