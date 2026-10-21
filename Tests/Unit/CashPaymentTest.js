// Vérifie les réponses réelles du handler, sans serveur ni commande du projet.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../Public/assets/js/checkout-payment.js'), 'utf8');

(async () => {
  for (const scenario of ['success', 'server', 'nonjson', 'network']) {
    const handlers = {}; let calls = 0, redirected = null, refreshed = 0, resolve;
    const radio = {checked: false};
    const button = {disabled: true, setAttribute() {}, removeAttribute() {}};
    const error = {textContent: ''};
    const form = {action: '/api/checkout/confirmer-cash', addEventListener(name, fn) {handlers[name] = fn;}, querySelector(selector) {return selector.includes('input') ? radio : selector.includes('submit') ? button : error;}};
    const request = new Promise(done => {resolve = done;});
    const window = {CartStore: {refresh: async () => {refreshed++;}}, location: {assign: url => {redirected = url;}}};
    vm.runInNewContext(source, {document: {querySelector: selector => selector === '[data-cash-form]' ? form : null}, window, FormData: class {}, fetch: async () => {calls++; await request; if(scenario==='network') throw new Error('Réseau indisponible'); return {ok: scenario==='success', json: async () => {if(scenario==='nonjson')throw new Error('HTML');return scenario==='success'?{success:true,url:'/checkout/confirmation?commande=1'}:{error:'Réservation expirée'};}};}});
    await handlers.submit({preventDefault(){}}); assert.equal(calls, 0, 'Sélection obligatoire');
    radio.checked = true; handlers.change(); assert.equal(button.disabled, false);
    const first = handlers.submit({preventDefault(){}});
    await handlers.submit({preventDefault(){}}); assert.equal(calls, 1, 'Double clic bloqué'); assert.equal(redirected, null, 'Attendre le serveur');
    resolve(); await first;
    if(scenario==='success') {assert.equal(redirected,'/checkout/confirmation?commande=1');assert.equal(refreshed,1);}
    else {assert.equal(redirected,null);assert.equal(refreshed,0);assert.ok(error.textContent);assert.equal(button.disabled,false);}
  }
  console.log('CASH_PAYMENT_JS_OK (sélection, attente serveur, double clic, erreurs, compteur, redirection).');
})();
