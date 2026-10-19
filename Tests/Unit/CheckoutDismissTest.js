// DOM minimal isolé : on exécute le vrai script, sans navigateur ni données réelles.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../Public/assets/js/checkout.js'), 'utf8');
class Element {
  constructor() { this.listeners = {}; this.attrs = {}; this.children = []; this.dataset = {}; this.value = ''; this.disabled = false; this.hidden = false; }
  addEventListener(type, callback) { (this.listeners[type] ||= []).push(callback); }
  setAttribute(name, value) { this.attrs[name] = value; }
  getAttribute(name) { return this.attrs[name] ?? null; }
  removeAttribute(name) { delete this.attrs[name]; }
  append(...children) { children.forEach((child) => { this.children.push(child); child.parentElement = this; }); }
  replaceChildren() { this.children = []; }
  contains(target) { return target === this || this.children.some((child) => child.contains(target)); }
  focus() { document.activeElement = this; }
  querySelector() { return this.children[0] ?? null; }
  querySelectorAll() { return []; }
  dispatchEvent(event) { event.target ||= this; (this.listeners[event.type] || []).forEach((fn) => fn(event)); if (event.bubbles) this.parentElement?.dispatchEvent(event); }
}
const page = new Element(), form = new Element(), order = new Element(), panel = new Element(), change = new Element(), fieldsBox = new Element(), zone = new Element();
const newRadio = new Element(); newRadio.value = '0'; newRadio.name = 'address_id'; newRadio.checked = true;
const address = new Element(); address.value = '12'; address.name = 'address_id';
address.dataset.address = JSON.stringify({prenom_destinataire:'TEST', nom_destinataire:'TEST', adresse:'TEST adresse', commune:'TEST', telephone:'+243000000000', id_zone_livraison: '2'});
panel.append(address, newRadio);
const field = new Element(); field.name = 'adresse';
zone.name = 'id_zone_livraison';
zone.options = [{value:'',textContent:'Choisissez une zone'}, {value:'1',textContent:'Zone A — $5.00',dataset:{name:'Zone A',fee:'$5.00'}}, {value:'2',textContent:'Zone B — $7.00',dataset:{name:'Zone B',fee:'$7.00'}}];
Object.defineProperty(zone, 'selectedOptions', {get: () => [zone.options.find((option) => option.value === zone.value)]});
const wrapper = new Element(); wrapper.append(zone); fieldsBox.append(field, wrapper); form.append(fieldsBox, panel);
fieldsBox.querySelectorAll = () => [field, zone];
const save = new Element(), makeDefault = new Element();
form.elements = [field, zone, save, makeDefault];
form.elements.namedItem = (name) => ({save_address:save, make_default:makeDefault, id_zone_livraison:zone})[name];
const label = new Element();
form.querySelector = (selector) => selector.includes('label') ? label : selector.includes('value="0"') ? newRadio : address.checked ? address : newRadio;
const selectors = {'[data-checkout-form]':form, '[data-order-form]':order, '#checkout-addresses':panel, '[data-change-address]':change, '[data-address-fields]':fieldsBox};
page.querySelector = (selector) => selectors[selector] ||= new Element();
page.querySelectorAll = () => [];
const document = {activeElement:null, listeners:[], querySelector:() => page, createElement:() => new Element(), addEventListener(type, fn, capture) { this.listeners.push({type, fn, capture}); }};
let requests = 0;
class FakeEvent { constructor(type, options = {}) { this.type = type; Object.assign(this, options); } preventDefault() {} }
vm.runInNewContext(source, {document, window:{}, Event:FakeEvent, AbortController, fetch:async () => { requests++; return {ok:true,json:async () => ({html:'TEST résumé'})}; }});
const picker = wrapper.children[1], trigger = picker.children[0], list = picker.children[1];
const click = (target) => { const event = new FakeEvent('click', {target, bubbles:true}); document.listeners.filter((item) => item.type === 'click').forEach((item) => item.fn(event)); target.dispatchEvent(event); };
const escape = (target) => target.dispatchEvent(new FakeEvent('keydown', {key:'Escape',bubbles:true}));
assert.equal(document.listeners.filter((item) => item.type === 'click').length, 1, 'Un seul gestionnaire global.');
click(trigger); assert.equal(list.hidden, false);
click(new Element()); assert.equal(list.hidden, true); assert.equal(trigger.getAttribute('aria-expanded'), 'false');
assert.equal(zone.value, ''); assert.equal(requests, 0, 'Fermer ne recalcule pas et ne choisit aucune zone.');
zone.value = '1';
click(trigger); click(new Element()); assert.equal(zone.value, '1', 'Valeur existante conservée.');
click(trigger); escape(list.children[0]); assert.equal(list.hidden, true); assert.equal(zone.value, '1'); assert.equal(requests, 0);
click(trigger); click(list.children[1]);
assert.equal(zone.value, '2'); assert.equal(list.hidden, true); assert.equal(requests, 1, 'Sélection réelle : un seul recalcul.');
click(change); assert.equal(panel.hidden, false);
click(address); assert.equal(panel.hidden, false, 'Clic interne sans changement : panneau conservé.');
click(new Element()); assert.equal(panel.hidden, true); assert.equal(change.getAttribute('aria-expanded'), 'false');
assert.equal(newRadio.checked, true); assert.equal(requests, 1, 'Aucune modification de l’adresse lors de la fermeture.');
click(change); escape(address); assert.equal(panel.hidden, true); assert.equal(newRadio.checked, true);
click(change); address.checked = true; newRadio.checked = false; address.dispatchEvent(new FakeEvent('change', {bubbles:true}));
assert.equal(panel.hidden, true); assert.equal(address.checked, true); assert.equal(requests, 2, 'Changement réel d’adresse conservé.');
// La fermeture du panneau ne déclenche ni sauvegarde ni nouvel appel serveur.
click(change); click(trigger); assert.equal(panel.hidden, true);
click(new Element()); assert.equal(list.hidden, true); assert.equal(address.checked, true); assert.equal(requests, 2);
console.log('CHECKOUT_DISMISS_OK (clic extérieur, sélection conservée, Escape, sélection normale, gestionnaire unique).');
