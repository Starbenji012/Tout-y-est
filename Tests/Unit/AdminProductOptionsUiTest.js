const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const {buildCandidates, mount, enhancePresentation} = require('../../Public/assets/js/admin-product-options.js');
for (let count = 1; count <= 3; count++) {
  const definitions = Array.from({length: count}, (_, i) => ({nom: 'Option ' + i, valeurs: ['A', 'B']}));
  assert.equal(buildCandidates(definitions).candidates.length, 2 ** count);
}
assert.ok(buildCandidates(Array.from({length: 4}, (_, i) => ({nom: '' + i, valeurs: ['A']}))).error);
for (const definitions of [[{nom: '', valeurs: ['A']}], [{nom: 'Matière', valeurs: ['']}], [{nom: 'Finition', valeurs: ['Écru', 'ecru']}], [{nom: 'Volume', valeurs: ['A']}, {nom: ' volume ', valeurs: ['B']}]]) assert.ok(buildCandidates(definitions).error);
const huge = buildCandidates([{nom: 'Matière', valeurs: Array.from({length: 100}, (_, i) => '' + i)}, {nom: 'Volume', valeurs: ['A', 'B']}]);
assert.ok(huge.error.includes('100')); assert.deepEqual(huge.candidates, []);
assert.deepEqual(buildCandidates([{nom: 'Stockage', valeurs: ['128 Go', '256 Go']}]).candidates.map(x => x.label), ['128 Go', '256 Go']);

// DOM minimal : vrais gestionnaires du composant, pas seulement des recherches CSS.
let document;
class Node {
  constructor(tag = 'div') { this.tagName = tag; this.children = []; this.attributes = {}; this.dataset = {}; this.events = {}; this.ownerDocument = document; this.value = ''; this.checked = false; this.hidden = false; this.className = ''; }
  setAttribute(key, value) { this.attributes[key] = value; if (key.startsWith('data-')) this.dataset[key.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = value; }
  removeAttribute(key) { delete this.attributes[key]; }
  hasAttribute(key) { return key in this.attributes; }
  append(...nodes) { nodes.forEach(node => { node.parentElement = this; this.children.push(node); }); }
  replaceChildren(...nodes) { this.children = []; this.append(...nodes); }
  remove() { this.parentElement.children = this.parentElement.children.filter(node => node !== this); }
  focus() { this.focused = true; }
  matches(selector) {
    return selector.split(',').some(part => {
      if (part.startsWith('.')) return this.className.split(' ').includes(part.slice(1));
      const attribute = part.match(/^\[([^=\]]+)(?:="([^"]*)")?\](:checked)?$/);
      if (attribute) { const value = attribute[1] in this ? this[attribute[1]] : this.attributes[attribute[1]]; return value !== undefined && (attribute[2] === undefined || String(value) === attribute[2]) && (!attribute[3] || this.checked); }
      return this.tagName === part;
    });
  }
  querySelectorAll(selector) { return this.children.flatMap(child => (child.matches(selector) ? [child] : []).concat(child.querySelectorAll(selector))); }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  closest(selector) { return this.matches(selector) ? this : this.parentElement?.closest(selector) || null; }
  addEventListener(type, handler) { this.events[type] = handler; }
}
document = {createElement: tag => new Node(tag), createTextNode: text => Object.assign(new Node('#text'), {textContent: text})};
const element = (tag, attribute) => { const node = new Node(tag); if (attribute) node.setAttribute(attribute, ''); return node; };
const editor = element('section', 'data-version-editor');
const form = element('form', 'data-version-builder'); editor.append(form);
const no = element('input'); no.name = 'mode'; no.value = 'no'; no.checked = true;
const yes = element('input'); yes.name = 'mode'; yes.value = 'yes';
const panel = element('div', 'data-options-panel');
const rows = element('div', 'data-option-rows');
const feedback = element('p', 'data-options-feedback');
const candidatePanel = element('div', 'data-candidate-panel');
const save = element('button', 'data-save-options'); save.value = 'configure';
const add = element('button', 'data-add-option');
const preview = element('button', 'data-preview-options'); preview.value = 'preview';
panel.append(rows, feedback, candidatePanel, save, add, preview); form.append(no, yes, panel);
const windowHandlers = {}; const previousListener = global.addEventListener; global.addEventListener = (name, callback) => { windowHandlers[name] = callback; };
try {
  mount(editor); assert.ok(panel.hidden, 'Simple sans complexité');
  assert.equal(add.type, 'button', 'Les actions locales ne deviennent pas une soumission implicite');
  no.checked = false; yes.checked = true; form.events.change({target: yes}); assert.equal(panel.hidden, false);
  const click = target => form.events.click({target, preventDefault() {}});
  click(add); let option = rows.querySelector('[data-option-row]'); option.querySelector('[data-option-name]').value = 'Matière'; option.querySelector('[data-option-value]').value = 'Coton';
  click(option.querySelector('[data-add-value]')); option.querySelectorAll('[data-option-value]')[1].value = 'Lin';
  click(add); option = rows.querySelectorAll('[data-option-row]')[1]; option.querySelector('[data-option-name]').value = 'Volume'; option.querySelector('[data-option-value]').value = 'Petit';
  click(option.querySelector('[data-add-value]')); option.querySelectorAll('[data-option-value]')[1].value = 'Grand';
  let prevented = false; form.events.submit({submitter: preview, preventDefault() { prevented = true; }});
  assert.ok(prevented); assert.equal(candidatePanel.querySelectorAll('[type="checkbox"]').length, 4); assert.equal(save.hidden, false);
  assert.equal(candidatePanel.querySelector('[name="reuse"]:checked'), null, 'Aucun choix de reprise automatique');
  const checks = candidatePanel.querySelectorAll('[type="checkbox"]'); checks[0].checked = false; form.events.change({target: checks[0]});
  const radios = candidatePanel.querySelectorAll('[type="radio"]'); radios[1].checked = true; form.events.change({target: radios[1]}); assert.ok(checks[1].checked);
  form.events.submit({submitter: save, preventDefault() { throw new Error('Soumission valide bloquée'); }}); assert.equal(form.attributes['aria-busy'], 'true');
  prevented = false; form.events.submit({submitter: save, preventDefault() { prevented = true; }}); assert.ok(prevented, 'Double soumission bloquée');
  windowHandlers.pageshow(); assert.equal(form.attributes['aria-busy'], undefined);
  form.events.input({target: rows.querySelector('[data-option-name]')}); assert.ok(save.hidden); assert.equal(candidatePanel.children.length, 0, 'Ancienne sélection invalidée après changement');
  click(add); assert.equal(rows.querySelectorAll('[data-option-row]').length, 3); assert.ok(add.disabled); click(add); assert.equal(rows.querySelectorAll('[data-option-row]').length, 3);
  click(rows.querySelectorAll('[data-option-row]')[2].querySelector('[data-remove-option]')); assert.equal(rows.querySelectorAll('[data-option-row]').length, 2); assert.equal(add.disabled, false);
  const values = rows.querySelectorAll('[data-option-row]')[1].querySelectorAll('[data-option-value]'); assert.equal(values[1].name, 'options[1][valeurs][1]');
} finally { global.addEventListener = previousListener; }
const view = fs.readFileSync(path.join(root, 'App/views/admin/product-options.php'), 'utf8');
// Formulaires déjà configurés : compteur réel, attente visible, retry bloqué et retour navigateur.
const configured = element('section'); const extend = element('form');
const count = element('span', 'data-selected-count'); const pendingStatus = element('p', 'data-version-pending'); pendingStatus.hidden = true;
const choice = element('input'); choice.name = 'selected[]'; choice.checked = false;
extend.append(count, choice); configured.append(extend, pendingStatus);
const resetHandlers = []; global.addEventListener = (name, fn) => { if (name === 'pageshow') resetHandlers.push(fn); };
try {
  enhancePresentation(configured); assert.equal(count.textContent, '0 sélectionnées');
  choice.checked = true; extend.events.change(); assert.equal(count.textContent, '1 sélectionnée');
  extend.events.submit({preventDefault() { throw new Error('Première soumission bloquée'); }});
  assert.equal(pendingStatus.hidden, false); assert.equal(extend.attributes['aria-busy'], 'true');
  let blocked = false; extend.events.submit({preventDefault() { blocked = true; }}); assert.ok(blocked);
  resetHandlers.forEach(fn => fn()); assert.equal(pendingStatus.hidden, true); assert.equal(extend.attributes['aria-busy'], undefined);
} finally { global.addEventListener = previousListener; }
assert.ok(view.includes('Nouvelles combinaisons disponibles'));
const salesView = fs.readFileSync(path.join(root, 'App/views/admin/product-sales.php'), 'utf8');
assert.ok(!view.includes('<table'), 'Les informations de vente ne sont pas dupliquées dans les options');
assert.ok(salesView.includes('ui-table ui-table--stacked') && salesView.includes('consultation uniquement') && salesView.includes('data-label="Référence SKU"'));
assert.ok(view.includes('data-selected-count') && view.includes('Ex. Couleur, Taille, Matière'));
assert.ok(view.includes('<noscript>') && view.includes('value="choose-mode"') && view.includes('name="_token"'));
assert.ok(view.includes('value="preview"') && view.includes('value="configure"'));
assert.ok(!view.includes('name="prix"') && !view.includes('name="stock"') && !view.includes('name="sku"'));
const css = fs.readFileSync(path.join(root, 'Public/assets/css/admin-products.css'), 'utf8');
assert.ok(css.includes('@media(max-width:48rem)') && css.includes('minmax(0,1fr)'));
assert.ok(css.includes('@media(min-width:64rem)') && css.includes('grid-template-columns:repeat(2,minmax(0,1fr))'));
assert.ok(css.includes('flex:1 0 100%') && !css.includes('minmax(0,1fr) auto auto'), 'Les boutons ne compriment plus les champs');
const shared = fs.readFileSync(path.join(root, 'Public/assets/css/ui-components.css'), 'utf8');
assert.ok(shared.includes('.ui-scope') && shared.includes('::file-selector-button') && shared.includes('.btn-danger'));
assert.ok(shared.includes(':focus-visible') && shared.includes(':disabled') && shared.includes('[aria-busy=true]') && shared.includes('prefers-reduced-motion'));
assert.ok(!shared.includes('!important') && !css.includes('!important'));
const formView = fs.readFileSync(path.join(root, 'App/views/admin/product-form.php'), 'utf8');
assert.ok(formView.includes('admin-products ui-scope') && formView.includes('btn btn-danger'));
const controller = fs.readFileSync(path.join(root, 'App/controllers/Admin/ProductController.php'), 'utf8');
assert.equal((controller.match(/ui-components\.css/g) || []).length, 1, 'Socle chargé seulement sur le formulaire produit');
console.log('ADMIN_PRODUCT_OPTIONS_UI_OK (mode, ajout/retrait options/valeurs, 1..3, plafond avant rendu, sélection, reprise explicite, invalidation, double clic, fallback).');
