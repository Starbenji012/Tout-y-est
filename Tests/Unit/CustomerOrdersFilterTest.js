// Simule les changements des selects natifs : seul un GET de formulaire est déclenché.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../Public/assets/js/customer-orders-filter.js'), 'utf8');
function setup(initial = 'all', available = ['2026-04'], enhanced = false) {
  const events = {}, navigations = [], pageEvents = {};
  const control = (name, value) => ({value, disabled: false, focused: false, addEventListener: (type, fn) => {events[name + type] = fn;}, focus() {this.focused = true;}});
  const period = control('period', initial), month = control('month', initial === 'month' ? available[0] : '');
  month.options = ['', ...available].map(value => ({value}));
  const field = {hidden: false};
  const form = {
    elements: {namedItem: name => name === 'period' ? period : month},
    querySelector: () => field,
    addEventListener: (type, fn) => {events['form' + type] = fn;},
    requestSubmit() {events.formsubmit();navigations.push({period: period.value, ...(month.disabled ? {} : {month: month.value})});},
  };
  const initialized = [];
  const SelectControl = enhanced ? {enhance: select => {initialized.push(select);return {sync() {}, focus() {select.focus();}};}} : undefined;
  vm.runInNewContext(source, {document: {querySelector: () => form}, window: {SelectControl, addEventListener: (type, fn) => {pageEvents[type] = fn;}}});
  return {period, month, field, events, navigations, pageEvents, initialized};
}
for (const value of ['all', 'current', 'previous']) {
  const app = setup('month');app.period.value = value;app.events.periodchange();
  assert.deepEqual(app.navigations, [{period: value}]);
  assert.equal(app.field.hidden, true);assert.equal(app.month.disabled, true);
}
const app = setup();
assert.equal(app.field.hidden, true);assert.equal(app.navigations.length, 0);
app.period.value = 'month';app.events.periodchange();
assert.equal(app.field.hidden, false);assert.equal(app.month.focused, true);
assert.equal(app.month.value, '');assert.equal(app.navigations.length, 0, 'Pas de sélection automatique du premier mois.');
app.events.monthchange();assert.equal(app.navigations.length, 0);
app.month.value = '2026-04';app.events.monthchange();
assert.deepEqual(app.navigations, [{period: 'month', month: '2026-04'}]);
app.events.monthchange();assert.equal(app.navigations.length, 1, 'Pas de navigation concurrente.');
app.pageEvents.pageshow();app.events.monthchange();assert.equal(app.navigations.length, 2, 'Retour navigateur utilisable.');
const empty = setup('all', []);empty.period.value = 'month';empty.events.periodchange();
assert.equal(empty.month.disabled, true);assert.equal(empty.navigations.length, 0);
const enhanced = setup('all', ['2026-04'], true);
assert.equal(enhanced.initialized.length, 2, 'Les deux selects sont enrichis.');
enhanced.period.value = 'month';enhanced.events.periodchange();
assert.equal(enhanced.month.focused, true);
enhanced.month.value = '2026-04';enhanced.events.monthchange();
assert.equal(enhanced.navigations.length, 1);
const template = fs.readFileSync(path.join(__dirname, '../../App/views/account/orders.php'), 'utf8');
assert.doesNotMatch(template, /'label'=>'Afficher'/);
assert.match(template, /<noscript>/);assert.match(template, /Appliquer la période/);
assert.doesNotMatch(template, /name="(?:page|id_utilisateur)"/);
assert.doesNotMatch(template, /La sélection applique automatiquement le filtre lorsque JavaScript est activé/);
assert.doesNotMatch(template, /orders-period-help/);
assert.match(template, /data-select-label/);
console.log('CUSTOMER_ORDERS_FILTER_OK (auto-GET, mois conditionnel, choix explicite, double changement, retour navigateur, fallback).');
