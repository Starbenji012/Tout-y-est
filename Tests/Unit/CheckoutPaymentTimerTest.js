// Test local isolé : aucune requête et aucune commande réelle.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../Public/assets/js/checkout-payment.js'), 'utf8');
const nodes = Object.fromEntries(['clock', 'title', 'running', 'help', 'notice', 'methods'].map((name) => [name, {textContent: '', hidden: false}]));
const selectors = {'[data-reservation-clock]': nodes.clock, '[data-reservation-title]': nodes.title, '[data-reservation-running]': nodes.running, '[data-reservation-help]': nodes.help, '[data-reservation-notice]': nodes.notice, '[data-payment-methods]': nodes.methods};
let monotonic = 0, wall = 100000, tick, cleared = false;
const handlers = {};
const page = {dataset: {reservationSeconds: '1722'}, querySelector: (selector) => selectors[selector]};
const context = {
  document: {querySelector: () => page, addEventListener: (name, fn) => { handlers[name] = fn; }},
  window: {addEventListener: (name, fn) => { handlers[name] = fn; }},
  performance: {now: () => monotonic, getEntriesByType: () => [{responseStart: 0}]},
  Date: {now: () => wall},
  setInterval: (fn) => { tick = fn; return 1; }, clearInterval: () => { cleared = true; },
};
vm.runInNewContext(source, context);
assert.equal(nodes.clock.textContent, '28:42');
monotonic = 1000; wall += 1000; tick();
assert.equal(nodes.clock.textContent, '28:41');
wall -= 60000; monotonic = 2000; tick();
assert.equal(nodes.clock.textContent, '28:40', 'Recul de l’horloge : pas de prolongation.');
wall = 110000; tick();
assert.equal(nodes.clock.textContent, '28:32');
wall = 100000; tick();
assert.equal(nodes.clock.textContent, '28:32', 'Une correction de l’horloge ne rallonge jamais le compteur.');
monotonic = 1722000; handlers.visibilitychange();
assert.equal(nodes.clock.textContent, '00:00');
assert.equal(nodes.methods.hidden, true);
assert.equal(nodes.running.hidden, true);
assert.equal(cleared, true);
assert.match(nodes.notice.textContent, /délai de réservation est écoulé/);
handlers.pageshow();
assert.equal(nodes.clock.textContent, '00:00', 'Retour à la page sans remise à zéro.');
assert.equal(page.dataset.reservationSeconds, '1722', 'Aucune modification de la durée serveur.');
console.log('CHECKOUT_PAYMENT_TIMER_OK (MM:SS, arrière-plan, horloge, expiration, aucun appel réseau).');
