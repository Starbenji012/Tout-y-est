const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const selects = [{id: 'admin-product-status'}, {id: 'admin-product-category'}];
const enhanced = [];
const handlers = {}, windowHandlers = {}, submissions = [];
const fields = {q: 'Sac test', statut: '', categorie: '2'};
let valid = true;
const form = {
  addEventListener: (event, handler) => { handlers[event] = handler; },
  requestSubmit: () => {
    // requestSubmit conserve la validation et l'événement submit natifs.
    if (!valid) return;
    let prevented = false;
    handlers.submit({preventDefault: () => { prevented = true; }});
    if (!prevented) submissions.push({...fields});
  }
};
vm.runInNewContext(fs.readFileSync(path.join(root, 'Public/assets/js/admin-products.js'), 'utf8'), {
  document: {
    querySelectorAll: selector => {assert.equal(selector, '.admin-products [data-select-control]'); return selects;},
    querySelector: selector => {assert.equal(selector, '.admin-products__filters'); return form;}
  },
  window: {SelectControl: {enhance: select => enhanced.push(select)}, addEventListener: (event, handler) => {windowHandlers[event] = handler;}}
});
assert.deepEqual(enhanced, selects);
fields.statut = 'brouillon';
handlers.change({target: {name: 'statut'}});
assert.deepEqual(submissions, [{q: 'Sac test', statut: 'brouillon', categorie: '2'}]);
handlers.change({target: {name: 'statut'}});
form.requestSubmit();
assert.equal(submissions.length, 1, 'Pas de double soumission avant navigation');
windowHandlers.pageshow();
fields.categorie = '1';
handlers.change({target: {name: 'categorie'}});
assert.deepEqual(submissions[1], {q: 'Sac test', statut: 'brouillon', categorie: '1'});
windowHandlers.pageshow();
fields.q = 'Casque';
assert.equal(handlers.input, undefined, 'Aucun traitement de chaque frappe');
assert.equal(handlers.keydown, undefined, 'Entrée reste une interaction native');
handlers.change({target: {name: 'q'}});
assert.equal(submissions.length, 2, 'Nom/SKU ne déclenche pas la recherche');
form.requestSubmit(); // Bouton ou Entrée : même soumission native.
assert.deepEqual(submissions[2], {q: 'Casque', statut: 'brouillon', categorie: '1'});
windowHandlers.pageshow();
valid = false;
handlers.change({target: {name: 'statut'}});
assert.equal(submissions.length, 3);
valid = true;
handlers.change({target: {name: 'statut'}});
assert.equal(submissions.length, 4, 'La validation ne verrouille pas les soumissions suivantes');
const view = fs.readFileSync(path.join(root, 'App/views/admin/products.php'), 'utf8');
for (const id of ['admin-product-status', 'admin-product-category']) {
  assert.ok(view.includes(`id="${id}"`));
  assert.ok(view.includes(`id="${id}-label" data-select-label`));
}
assert.ok(view.includes('method="get"'));
assert.ok(view.includes('type="submit"'));
assert.ok(view.includes('action="/admin/produits"'));
assert.ok(view.includes('name="q"'));
assert.ok(view.includes('href="/admin/produits">Effacer les filtres</a>'));
assert.ok(!view.includes('onsubmit='), 'Formulaire natif et bouton utilisables sans JS');
assert.ok(view.includes('href="/admin/produit/ajouter"'));
assert.ok(view.includes('href="/admin/produit?id='));
assert.ok(view.includes('Sans image'));
assert.ok(view.includes('Aucun produit trouvé'));
assert.ok(view.includes('sur variantes inactives'));
assert.ok(view.includes('Stock disponible</dt>'));
assert.ok(!view.includes('Stock disponible total'));
assert.ok(!view.includes('y compris inactives.'));
assert.ok(view.includes('admin-products__head'));
assert.ok(!view.includes('admin-order-card admin-products__item'));
const css = fs.readFileSync(path.join(root, 'Public/assets/css/admin-products.css'), 'utf8');
assert.ok(css.includes('@media(max-width:48rem)'));
assert.ok(css.includes('@media(max-width:25rem)'));
assert.ok(css.includes('minmax(0,1fr)'));
assert.match(css, /grid-template-columns:3\.5rem minmax\(0,2fr\)/);
assert.ok(css.includes('.admin-products__head { display:none; }'));
assert.ok(css.includes('width:3.5rem; height:3.5rem;'));
assert.ok(!css.includes('min-width:14rem'));
// Une rangée d'identité distincte, puis quatre cellules dans leur ordre HTML.
assert.match(view, /admin-products__intro[\s\S]*admin-products__image[\s\S]*admin-products__identity[\s\S]*<\/div>\s*<dl><dt>Statut<\/dt>[\s\S]*<dl><dt>Variantes<\/dt>[\s\S]*<dl><dt>Prix<\/dt>[\s\S]*<dl><dt>Stock disponible<\/dt>/);
const desktop = css.split('@media(max-width:48rem)')[0];
const mobile = css.split('@media(max-width:48rem)')[1].split('@media(max-width:25rem)')[0];
assert.ok(desktop.includes('.admin-products__intro { display:contents; }'));
assert.match(mobile, /\.admin-products__intro \{ display:grid; grid-column:1\/-1; grid-template-columns:3\.5rem minmax\(0,1fr\)/);
assert.match(mobile, /\.admin-products__item \{ grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/);
assert.ok(mobile.includes('padding-bottom:var(--spacing-sm); border-bottom:var(--border)'));
// 375 et 768 px utilisent la même grille mobile ; au-delà, les six colonnes restent intactes.
const breakpoint = Number(css.match(/@media\(max-width:(\d+)rem\)/)[1]) * 16;
for (const [width, mobileExpected] of [[375, true], [768, true], [1280, false]]) {
  const applied = width <= breakpoint ? mobile : desktop;
  const intro = applied.match(/\.admin-products__intro \{([^}]+)\}/)[1];
  assert.ok(intro.includes(mobileExpected ? 'display:grid' : 'display:contents'), `Identité à ${width}px`);
  if (mobileExpected) assert.ok(intro.includes('grid-column:1/-1'), `Identité sur toute la rangée à ${width}px`);
  else assert.match(applied, /grid-template-columns:3\.5rem minmax\(0,2fr\)/);
}
assert.ok(!css.includes('overflow-x:scroll'));
console.log('ADMIN_PRODUCTS_UI_OK (select-control partagé, GET natif/fallback, contrôles nommés, responsive, aucun bouton mort).');
