const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const select = {};
let enhanced = 0;
vm.runInNewContext(fs.readFileSync(path.join(root, 'Public/assets/js/admin-orders.js'), 'utf8'), {
  document: {querySelectorAll: selector => {assert.equal(selector, '[data-select-control]'); return [select];}},
  window: {SelectControl: {enhance: value => {assert.equal(value, select); enhanced++;}}}
});
assert.equal(enhanced, 1);
const list = fs.readFileSync(path.join(root, 'App/views/admin/orders.php'), 'utf8');
assert.match(list, /method="get"/);
assert.match(list, /data-select-control/);
assert.match(list, /http_build_query\(\$filters/);
for (const name of ['orders', 'order-detail']) {
  const view = fs.readFileSync(path.join(root, `App/views/admin/${name}.php`), 'utf8');
  assert.doesNotMatch(view, /\/api\/admin\/commandes|Commencer la préparation|Confirmer l’encaissement|Marquer livrée/);
}
console.log('ADMIN_ORDERS_UI_OK (select partagé, GET, pagination, aucune mutation).');
