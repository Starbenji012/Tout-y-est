const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.resolve(__dirname, '../../Public/assets/js/admin-product-form.js'), 'utf8');
let handler, restore, accepted = false, confirms = 0;
const attributes = {};
const gallery = {
  addEventListener: (event, callback) => { if (event === 'submit') handler = callback; },
  setAttribute: (key, value) => attributes[key] = value,
  removeAttribute: key => delete attributes[key]
};
vm.runInNewContext(script, {
  document: {querySelectorAll: selector => selector === '[data-gallery-actions]' ? [gallery] : [], querySelector: () => null},
  window: {confirm: () => { confirms++; return accepted; }, addEventListener: (event, callback) => { restore = callback; }}
});
let blocked = false;
const submit = value => { blocked = false; handler({submitter: {value}, preventDefault: () => blocked = true}); };
submit('delete'); assert.ok(blocked); assert.equal(confirms, 1); assert.equal(attributes['aria-busy'], undefined);
accepted = true; submit('delete'); assert.ok(!blocked); assert.equal(attributes['aria-busy'], 'true');
submit('delete'); assert.ok(blocked); assert.equal(confirms, 2, 'Retry bloqué avant seconde confirmation');
restore(); assert.equal(attributes['aria-busy'], undefined);
submit('up'); assert.ok(!blocked); assert.equal(confirms, 2, 'Déplacement sans confirmation destructive');
restore(); submit('primary'); assert.ok(!blocked);
assert.ok(!script.includes('fetch('), 'POST classique, serveur autoritaire');
console.log('ADMIN_PRODUCT_GALLERY_UI_OK (confirmation, annulation, double clic, retour navigateur, POST natif).');
