const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const buttons = [{disabled:false},{disabled:false},{disabled:true}];
let pageshow, approved = false, confirmations = 0;
const forms = buttons.map((button,i) => ({
  dataset:i===0?{tourConfirm:'Confirmer le cash physiquement reçu ?'}:{},
  querySelectorAll:()=>[button],setAttribute(){},removeAttribute(){},
  addEventListener(type,handler){ if(type==='submit')this.submit=handler; }
}));
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname,'../../Public/assets/js/courier-tours.js'),'utf8'),{
  document:{querySelectorAll:()=>forms},
  window:{confirm:()=>{confirmations++;return approved;},addEventListener:(type,fn)=>{if(type==='pageshow')pageshow=fn;}}
});
let cancelled=false;
forms[0].submit({preventDefault(){cancelled=true;}});assert.equal(cancelled,true);assert.equal(buttons[0].disabled,false);
approved=true;cancelled=false;forms[0].submit({preventDefault(){cancelled=true;}});assert.equal(cancelled,false);assert.ok(buttons.every(b=>b.disabled));
forms[1].submit({preventDefault(){cancelled=true;}});assert.equal(cancelled,true,'Une seule soumission globale');
pageshow();assert.equal(buttons[0].disabled,false);assert.equal(buttons[2].disabled,true,'Indisponibilité préexistante conservée au retour navigateur');
const before=confirmations;forms[1].submit({preventDefault(){throw Error('Le départ natif doit fonctionner');}});assert.equal(confirmations,before,'Pas de confirmation redondante du départ');
pageshow();
const remittance=fs.readFileSync(path.resolve(__dirname,'../../App/views/admin/remittances.php'),'utf8').split('</form>')[0];
assert.match(remittance,/name="mode" value="exact"/);
assert.doesNotMatch(remittance,/data-tour-confirm|name="montants\[/,'La remise exacte demande un seul clic, sans saisie ni confirmation supplémentaire');
forms[1].submit({preventDefault(){throw Error('La remise exacte doit produire un POST natif unique');}});
assert.equal(confirmations,before);assert.ok(buttons.every(b=>b.disabled),'Protection double clic toujours active');
const view=fs.readFileSync(path.resolve(__dirname,'../../App/views/courier/tours.php'),'utf8');
const detail=fs.readFileSync(path.resolve(__dirname,'../../App/views/courier/delivery-detail.php'),'utf8');
assert.match(view,/data-delivery-group=/);assert.match(view,/activeParcel!==null/);
assert.doesNotMatch(view,/name="tentative"/,'Aucun traitement concurrent de plusieurs colis depuis la liste');
assert.match(detail,/data-active-delivery=/);assert.match(detail,/name="tentative" value="<\?= \$attempt \?>"/);
assert.match(detail,/data-tour-confirm="<\?= \$escape\('Confirmer la livraison de '\.\$reference/,'La confirmation rappelle la référence ouverte');
assert.match(detail,/<details class="courier-exception">/,'Incident replié par défaut, accessible sans JS');
console.log('COURIER_TOURS_JS_OK (POST natif, confirmation cash, double clic, navigation retour, disabled).');
