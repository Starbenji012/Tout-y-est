// Vérifie les points de présentation communs sans inventer d'avis ni d'images.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
for (const file of ['components/product-card.php', 'components/quick-view.php', 'products/show.php']) {
  assert.doesNotMatch(read('App/views/' + file), /data-lucide="star"|class="[^"\n]*__rating/);
}
assert.doesNotMatch(read('App/views/components/catalog-filters.php'), /Note minimale/);
assert.doesNotMatch(read('App/views/shop/index.php'), /Mieux notés/);
for (const file of ['account/order-detail.php', 'checkout/confirmation.php']) {
  assert.match(read('App/views/' + file), /components\/order-delivery\.php/);
}
const css = read('Public/assets/css/account.css');
assert.match(css, /customer-order--compact/);
assert.match(css, /@media \(max-width: 639px\)/);
assert.match(css, /overflow-wrap: anywhere/);
const detail = read('App/views/account/order-detail.php');
assert.doesNotMatch(detail, /Illustration actuelle/);
assert.match(detail, /customer-order-detail__left/);
assert.match(detail, /customer-order-detail__delivery/);
assert.match(css, /customer-order-detail__left\s*\{\s*display: grid; align-content: start/);
assert.match(css, /customer-order-detail__left\s*\{\s*display: contents/);
assert.match(css, /customer-order-detail > aside\s*\{\s*order: 1/);
assert.match(css, /customer-order-detail__delivery\s*\{\s*order: 2/);
assert.match(css, /grid-template-columns: minmax\(0, 1\.1fr\) minmax\(0, 2fr\) auto/);
assert.match(read('App/views/account/orders.php'), /method="get"/);
assert.match(read('App/views/components/order-delivery.php'), /order-delivery__recipient/);
console.log('CUSTOMER_ORDERS_UX_OK');
