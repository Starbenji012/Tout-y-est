const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {URL} = require('node:url');
(async () => {
  const source=fs.readFileSync(path.resolve(__dirname,'../../Public/assets/js/admin-order-actions.js'),'utf8');
  function setup(action='prepare', transport) {
    let reloads=0, confirmations=0, approved=true;
    const message={textContent:''}, storage=new Map(), buttons=[{disabled:true},{disabled:true}];
    const forms=[action,'cancel'].map((name,i)=>({dataset:{orderAction:name,orderReference:'TYE-001'},action:'/api/admin/commandes/'+name,data:{commande:'1',_token:'csrf',...(name==='ship'?{livreur:'TEST Livreur'}:{})},reportValidity:()=>true,querySelectorAll:()=>[buttons[i]],setAttribute(){},removeAttribute(){},addEventListener(type,handler){this.submit=handler;}}));
    const context={URL,location:{href:'http://localhost/admin/commande?reference=TYE-001'},sessionStorage:{getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},FormData:class{constructor(form){this.values=form.data;}get(k){return this.values[k];}},fetch:transport,document:{querySelectorAll:()=>forms,querySelector:()=>message},window:{confirm:()=>{confirmations++;return approved;},location:{reload:()=>reloads++}}};
    vm.runInNewContext(source,context);
    return {forms,buttons,message,storage,setApproved:v=>approved=v,reloads:()=>reloads,confirmations:()=>confirmations};
  }
  let calls=0, finish;
  const ui=setup('prepare',async(url,request)=>{calls++;assert.equal(request.method,'POST');assert.equal(request.credentials,'same-origin');assert.equal(request.body.get('_token'),'csrf');assert.equal(request.body.get('montant'),undefined);return await new Promise(resolve=>finish=resolve);});
  const event=()=>({preventDefault(){}});
  ui.setApproved(false);await ui.forms[0].submit(event());assert.equal(calls,0,'Confirmation refusée');
  ui.setApproved(true);const pending=ui.forms[0].submit(event());await ui.forms[0].submit(event());await ui.forms[1].submit(event());
  assert.equal(calls,1,'Double clic et action concurrente bloqués');assert.ok(ui.buttons.every(b=>b.disabled));
  finish({ok:true,json:async()=>({success:true,message:'Préparation commencée'})});await pending;
  assert.equal(ui.reloads(),1);assert.equal(ui.message.textContent,'Préparation commencée');
  assert.equal(JSON.parse([...ui.storage.values()][0]).reference,'TYE-001');
  const denied=setup('cancel',async()=>({ok:false,json:async()=>({error:'Session expirée.'})}));await denied.forms[0].submit(event());assert.equal(denied.reloads(),0);assert.equal(denied.message.textContent,'Session expirée.');assert.ok(denied.buttons.every(b=>!b.disabled));
  const invalid=setup('collectCash',async()=>({ok:false,json:async()=>{throw new Error('HTML');}}));await invalid.forms[0].submit(event());assert.equal(invalid.reloads(),0);assert.match(invalid.message.textContent,/Réponse illisible/);
  const network=setup('deliver',async()=>{throw new Error('Réseau interrompu');});await network.forms[0].submit(event());assert.equal(network.reloads(),0);assert.ok(network.buttons.every(b=>!b.disabled));
  const ship=setup('ship',async(url,request)=>{assert.equal(request.body.get('livreur'),'TEST Livreur');return {ok:true,json:async()=>({success:true})};});await ship.forms[0].submit(event());assert.match(ship.message.textContent,/TEST Livreur/);
  console.log('ADMIN_ORDER_WORKFLOW_JS_OK (confirmations, CSRF, attente serveur, double clic, erreurs, livreur, relecture).');
})();
