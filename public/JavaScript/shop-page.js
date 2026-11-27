import { loadShopCatalog } from './shop-catalog.js';
import { setupShopPurchases } from './shop-purchases.js';

document.addEventListener('DOMContentLoaded', () => {
    setupShopPurchases();
    loadShopCatalog();
});
