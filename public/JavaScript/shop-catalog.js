import { requestShop, shopRequestHeaders } from './shop-api.js';
import { shopItemImages } from './shop-item-assets.js';

const healingItemSlugs = [
    'potion',
    'super-potion',
    'hyper-potion',
    'max-potion',
    'full-restore',
    'antidote',
    'burn-heal',
    'ice-heal',
    'awakening',
    'paralyze-heal',
    'full-heal',
    'revive',
];

const categoryOrder = [
    'Recuperacao de HP',
    'Cura de status',
    'Reviver',
    'Pedras Mega',
];

const categoryTitles = {
    'Recuperacao de HP': 'Recuperação de HP',
    'Cura de status': 'Cura de status',
    Reviver: 'Reviver',
    'Pedras Mega': 'Mega Evolução',
};

export async function loadShopCatalog() {
    const list = document.getElementById('listaItensLoja');
    if (!list) return;

    try {
        let shop = await requestShop('listar');
        const synchronized = await synchronizeHealingDescriptions(shop);
        if (synchronized) shop = await requestShop('listar');

        if (!Array.isArray(shop.itens) || shop.itens.length === 0) {
            throw new Error('A loja ainda não tem itens salvos.');
        }

        updateBalances(shop.saldo);
        renderCatalog(list, shop);
    } catch (error) {
        list.innerHTML = `<p>${escapeHtml(error.message || 'Não foi possível carregar a loja.')}</p>`;
        console.error(error);
    }
}

async function synchronizeHealingDescriptions(shop) {
    const message = document.getElementById('mensagemLoja');
    const descriptions = await Promise.all(healingItemSlugs.map(async (slug) => {
        try {
            const response = await fetch(`https://pokeapi.co/api/v2/item/${slug}`);
            if (!response.ok) throw new Error(`Falha ao buscar ${slug} na PokeAPI.`);

            const item = await response.json();
            const effect = item.effect_entries.find((entry) => entry.language.name === 'en');
            return {
                slug,
                descricao: effect ? effect.short_effect : 'Item curativo.',
            };
        } catch (error) {
            console.error(error);
            return null;
        }
    }));

    const validDescriptions = descriptions.filter(Boolean);
    if (validDescriptions.length === 0) {
        if (message) {
            message.textContent =
                'Não foi possível atualizar os itens pela PokeAPI. Exibindo os itens já salvos no banco.';
        }
        return false;
    }

    try {
        await requestShop('sincronizar', {
            method: 'POST',
            headers: shopRequestHeaders(true),
            body: JSON.stringify({ itens: validDescriptions }),
        });
    } catch (error) {
        console.error(error);
        if (message) {
            message.textContent =
                'Não foi possível atualizar os itens pela PokeAPI. Exibindo os itens já salvos no banco.';
        }
        return false;
    }

    if (message && validDescriptions.length < healingItemSlugs.length) {
        message.textContent =
            'A PokeAPI retornou apenas parte dos itens; o catálogo foi sincronizado parcialmente.';
    }

    return true;
}

function renderCatalog(list, shop) {
    const groups = new Map();
    const ownedMegaPokemon = new Set(
        (shop.ids_pokemon_com_pedra_mega || []).map(Number)
    );

    shop.itens.forEach((item) => {
        const category = item.categoria || 'Outros';
        if (!groups.has(category)) groups.set(category, []);
        groups.get(category).push(item);
    });

    const categories = [...groups.entries()].sort(([first], [second]) => {
        const firstOrder = categoryOrder.indexOf(first);
        const secondOrder = categoryOrder.indexOf(second);
        return (firstOrder < 0 ? categoryOrder.length : firstOrder)
            - (secondOrder < 0 ? categoryOrder.length : secondOrder)
            || first.localeCompare(second);
    });

    list.innerHTML = categories.map(([category, items]) => `
        <section class="grupo-categoria">
            <header class="categoria-loja-cabecalho">
                <h3>${escapeHtml(categoryTitles[category] || category)}</h3>
                <span>${items.length} ${items.length === 1 ? 'item' : 'itens'}</span>
            </header>
            <div class="grade-produtos">
                ${items.map((item) => renderItem(item, shop, ownedMegaPokemon)).join('')}
            </div>
        </section>
    `).join('');
}

function renderItem(item, shop, ownedMegaPokemon) {
    const megaStone = Number(item.evolucao) === 1;
    const pokemonId = Number(item.id_pokedex_mega);
    const lockedByArea = megaStone && Number(shop.area_atual) < 3;
    const alreadyOwned = megaStone && ownedMegaPokemon.has(pokemonId);
    const locked = lockedByArea || alreadyOwned;
    const reason = lockedByArea
        ? 'Desbloqueia ao chegar na Área 3.'
        : alreadyOwned
            ? 'Você já possui uma Pedra Mega deste Pokémon.'
            : megaStone
                ? 'Uma pedra por Pokémon; não é consumida ao usar.'
                : '';
    const buttonText = lockedByArea ? 'Área 3' : alreadyOwned ? 'Já possui' : 'Comprar';

    return `
        <article class="produto ${locked ? 'produto-bloqueado' : ''}"
                 data-evolucao="${megaStone ? 1 : 0}"
                 data-pokemon-mega="${megaStone ? pokemonId : ''}">
            <img class="produto-imagem"
                 src="assets/itens/${escapeHtml(shopItemImages[item.nome] || 'potionNV1.png')}"
                 alt="" width="72" height="72">
            <h3>${escapeHtml(item.nome)}</h3>
            <p>${escapeHtml(item.descricao || 'Item curativo.')}</p>
            <p class="produto-quantidade">
                No inventário: <span id="quantidade-item-${Number(item.id_item)}">${Number(item.quantidade)}</span>
            </p>
            <p class="precoItem"><strong>${Number(item.preco)} Pokédollars</strong></p>
            ${reason ? `<p class="produto-bloqueio">${escapeHtml(reason)}</p>` : ''}
            <button type="button" data-comprar-item="${Number(item.id_item)}" ${locked ? 'disabled' : ''}>
                ${buttonText}
            </button>
        </article>
    `;
}

function updateBalances(balance) {
    ['saldoPokemon', 'saldoHeader'].forEach((id) => {
        const element = document.getElementById(id);
        if (element && balance !== undefined) element.textContent = balance;
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
