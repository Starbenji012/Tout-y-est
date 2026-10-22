// DOM minimal : exécute le composant réel, sans navigateur ni dépendance supplémentaire.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const handlers = {};
let focused;
class Node {
  constructor(tag) {
    this.tagName = tag;this.children = [];this.attributes = {};this.events = {};this.hidden = false;this.disabled = false;
    this.classes = new Set();this.classList = {toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name)};
  }
  append(...nodes) {nodes.forEach(node => {node.parent = this;this.children.push(node);});}
  after(node) {node.parent = this.parent;this.parent.children.splice(this.parent.children.indexOf(this) + 1, 0, node);}
  contains(node) {return node === this || this.children.some(child => child.contains(node));}
  setAttribute(name, value) {this.attributes[name] = value;}
  getAttribute(name) {return this.attributes[name] ?? null;}
  removeAttribute(name) {delete this.attributes[name];}
  addEventListener(name, fn) {(this.events[name] ??= []).push(fn);}
  dispatchEvent(event) {event.target ??= this;(this.events[event.type] ?? []).forEach(fn => fn(event));return true;}
  focus() {focused = this;}
  querySelector() {return this.caption;}
  scrollIntoView() {}
}
class Event {
  constructor(type, options = {}) {this.type = type;Object.assign(this, options);}
  preventDefault() {this.prevented = true;}
}
const context = {Event, window: {}, document: {createElement: tag => new Node(tag), addEventListener: (name, fn) => {handlers[name] = fn;}}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../Public/assets/js/select-control.js'), 'utf8'), context);
function make(id) {
  const label = new Node('label');label.caption = new Node('span');label.caption.id = id + '-label';
  const select = new Node('select');select.id = id;select.labels = [label];select.options = [
    {textContent: 'Toutes', value: 'all', disabled: false}, {textContent: 'Ce mois', value: 'current', disabled: false},
    {textContent: 'Désactivée', value: 'off', disabled: true}, {textContent: 'Choisir', value: 'month', disabled: false},
  ];
  select.selectedIndex = 0;
  Object.defineProperty(select, 'selectedOptions', {get: () => [select.options[select.selectedIndex]]});
  label.append(select);select.setAttribute('aria-describedby', 'help');
  return {label, select, picker: context.window.SelectControl.enhance(select)};
}
const {select, picker, label} = make('period');let changes = 0;select.addEventListener('change', () => {changes++;});
assert.equal(select.hidden, true);assert.equal(label.htmlFor, 'period-trigger');
assert.equal(picker.trigger.getAttribute('role'), 'combobox');assert.equal(picker.list.getAttribute('role'), 'listbox');
assert.equal(picker.trigger.getAttribute('aria-describedby'), 'help');assert.equal(picker.value.textContent, 'Toutes');
assert.equal(context.window.SelectControl.enhance(select), picker, 'Pas de doublon DOM.');
const key = value => {const event = new Event('keydown', {key: value});picker.trigger.dispatchEvent(event);return event;};
key('Enter');assert.equal(picker.list.hidden, false);assert.equal(picker.trigger.getAttribute('aria-expanded'), 'true');
key('ArrowDown');assert.equal(picker.active, 1);assert.equal(select.selectedIndex, 0, 'Naviguer ne sélectionne pas.');
key('ArrowDown');assert.equal(picker.active, 3, 'Option désactivée ignorée.');
key('Escape');assert.equal(picker.list.hidden, true);assert.equal(select.selectedIndex, 0);assert.equal(changes, 0);
key(' ');key('End');key('Enter');
assert.equal(select.selectedIndex, 3);assert.equal(changes, 1);assert.equal(picker.list.hidden, true);assert.equal(focused, picker.trigger);
assert.equal(picker.options[3].getAttribute('aria-selected'), 'true');
key('Enter');key('Home');assert.equal(picker.active, 0);key('ArrowUp');assert.equal(picker.active, 3);
const tab = key('Tab');assert.equal(picker.list.hidden, true);assert.notEqual(tab.prevented, true, 'Tab conserve son parcours natif.');
picker.open();handlers.pointerdown({target: picker.options[0]});assert.equal(picker.list.hidden, false);
handlers.pointerdown({target: new Node('outside')});assert.equal(picker.list.hidden, true);assert.equal(select.selectedIndex, 3);
picker.open();const click = new Event('click');picker.options[1].dispatchEvent(click);
assert.equal(click.prevented, true, 'Empêche la réactivation du label englobant.');
assert.equal(select.selectedIndex, 1);assert.equal(picker.value.textContent, 'Ce mois');assert.equal(changes, 2);
picker.open();const second = make('month').picker;second.open();assert.equal(picker.list.hidden, true);
select.disabled = true;picker.sync();picker.open();assert.equal(picker.trigger.disabled, true);assert.equal(picker.list.hidden, true);
second.root.dispatchEvent(new Event('focusout', {relatedTarget: new Node('outside')}));assert.equal(second.list.hidden, true);
const css = fs.readFileSync(path.join(__dirname, '../../Public/assets/css/select-control.css'), 'utf8');
assert.match(css, /position: absolute/);assert.match(css, /overflow-y: auto/);assert.match(css, /aria-selected/);
console.log('SELECT_CONTROL_OK (ARIA, clavier, Escape, Tab, clic extérieur, choix, disabled, synchronisation, instance unique).');
