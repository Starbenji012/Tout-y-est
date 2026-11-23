const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const handlers = {}, selectHandlers = {}, windowHandlers = {}, attributes = {};
const feedback = {hidden: true};
let enhanced = 0, focused = false, serverInvalid = false;
const trigger = {setAttribute: (k, v) => attributes[k] = v, removeAttribute: k => delete attributes[k], focus: () => focused = true};
const select = {id: 'product-category', hidden: false, getAttribute: key => key === 'aria-invalid' && serverInvalid ? 'true' : null, addEventListener: (k, v) => selectHandlers[k] = v};
const form = {
  querySelectorAll: () => [select], querySelector: () => feedback,
  addEventListener: (k, v) => handlers[k] = v,
  setAttribute: (k, v) => attributes[k] = v, removeAttribute: k => delete attributes[k]
};
const context = {
  document: {querySelector: () => form, querySelectorAll: () => [], getElementById: () => trigger},
  window: {SelectControl: {enhance: item => {enhanced++; item.hidden = true;}}, addEventListener: (k, v) => windowHandlers[k] = v}
};
vm.runInNewContext(fs.readFileSync(path.join(root, 'Public/assets/js/admin-product-form.js'), 'utf8'), context);
assert.equal(enhanced, 1);
let prevented = false;
selectHandlers.invalid({preventDefault: () => prevented = true});
assert.ok(prevented && focused && !feedback.hidden);
assert.equal(attributes['aria-invalid'], 'true');
selectHandlers.change(); assert.ok(feedback.hidden); assert.equal(attributes['aria-invalid'], undefined);
assert.equal(handlers.change, undefined, 'Aucun POST au changement de champ');
handlers.submit({preventDefault: () => {throw new Error('Premier POST bloqué');}});
assert.equal(attributes['aria-busy'], 'true');
prevented = false; handlers.submit({preventDefault: () => prevented = true}); assert.ok(prevented);
windowHandlers.pageshow(); assert.equal(attributes['aria-busy'], undefined);
handlers.submit({preventDefault: () => {throw new Error('Retour navigateur bloqué');}});
const view = fs.readFileSync(path.join(root, 'App/views/admin/product-form.php'), 'utf8');
assert.ok(view.includes('method="post"') && view.includes('name="_token"'));
assert.ok(view.includes('name="stock_initial"') && view.includes('<?php if (!$editing): ?>'));
assert.ok(view.includes('name="statut" value="brouillon"'));
assert.ok(view.includes('photo nécessaire'));
assert.ok(!view.includes('name="taux"') && !view.includes('name="prix_usd"'));
assert.ok(view.includes('type="file"') && view.includes('enctype="multipart/form-data"'));
assert.ok(view.includes('action="/admin/produits/photo"'));
assert.ok(view.includes('Photos du produit') && view.includes(' / 6 photos'));
assert.ok(view.includes('count($photos)<6') && view.includes('Principale'));
assert.ok(view.includes('accept="image/jpeg,image/png,image/webp"'));
const script = fs.readFileSync(path.join(root, 'Public/assets/js/admin-product-form.js'), 'utf8');
assert.ok(!script.includes('fetch(') && !script.includes('2450'));
serverInvalid = true;
vm.runInNewContext(script, context);
assert.equal(attributes['aria-invalid'], 'true', 'Erreur serveur reportée sur le select visible');
selectHandlers.change();
assert.equal(attributes['aria-invalid'], 'true', 'Erreur serveur conservée avant nouvelle validation');
assert.ok(view.includes('Référence (SKU) — facultatif'));
assert.ok(view.includes('Code unique permettant d’identifier le produit. Laissez vide pour le générer automatiquement.'));
assert.ok(view.includes('Informations de vente') && !view.includes('Variantes — consultation'));
assert.ok(view.includes('admin-product-sales__details'));
assert.ok(view.includes('$attributes(\'prix\',\'product-price-help\')'));
assert.ok(view.includes('$inlineError(\'prix\')'));
assert.ok(view.includes('admin-product-notice--success'));
const css = fs.readFileSync(path.join(root, 'Public/assets/css/admin-products.css'), 'utf8');
assert.ok(css.includes('@media(max-width:60rem)'));
assert.ok(css.includes('.admin-products .select-control__trigger:is(:focus-visible,[aria-expanded="true"])'));
console.log('ADMIN_PRODUCTS_FORM_UI_OK (POST natif, selects accessibles, brouillon, double clic, galerie et upload séparé, aucun taux client).');
