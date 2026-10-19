// Intégration réelle du gestionnaire commun : pas de moteur Checkout parallèle.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const read = (file) => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');
const motion = read('Public/assets/css/animations.css');
const checkoutCss = read('Public/assets/css/checkout.css');
const step1 = read('App/views/checkout/index.php');
const step2 = read('App/views/checkout/payment.php');
const homeHeader = read('App/views/components/section-header.php');
const sharedType = homeHeader.match(/data-motion="([^"]+)"/)[1];
assert.equal(sharedType, 'section');
for (const [html, count] of [[step1,2],[step2,4]]) {
  const types = [...html.matchAll(/data-motion="([^"]+)"/g)].map(match=>match[1]);
  assert.equal(types.length,count); assert.ok(types.every(type=>type===sharedType),'Même attribut que les sections de l’accueil.');
}
assert.doesNotMatch(step1+step2+motion+read('Public/assets/css/variables.css'), /checkout-load-enter|data-checkout-enter|duration-checkout-enter/);
assert.match(motion,/\.motion-enabled \[data-motion="section"\]/);
assert.match(motion,/\.motion-enabled \[data-motion\]\.is-motion-visible/);
assert.doesNotMatch(checkoutCss,/(?:\.checkout-panel|\.checkout-information|\.checkout-reservation|\.checkout-payment-methods|\.checkout-payment-summary|\.checkout-payment-delivery|\.checkout-summary)\s*\{[^{}]*(?:opacity\s*:|transform\s*:)/,'Le CSS Checkout ne neutralise pas les apparitions de ses grands blocs.');
const layout = read('App/views/layouts/main.php');
assert.match(layout,/href="\/assets\/css\/animations.css"/);
assert.ok(layout.indexOf('/assets/js/animations.js')<layout.indexOf('/assets/js/app.js'));
const engine = read('Public/assets/js/animations.js');
const app = read('Public/assets/js/app.js');
// Exécute app.js, qui initialise exactement le même système sur toutes les pages.
for (const [reduced, supportsObserver] of [[false,true],[true,true],[false,false]]) {
  let callback, enabled=false, observerCount=0; const observed=[], unobserved=[], frames=[];
  const makeNode = (i) => ({dataset:{motion:sharedType,motionDelay:String(i*40)},style:{delay:null,setProperty(name,value){this.delay=value;}},classList:{classes:new Set(),get visible(){return this.classes.has('is-motion-visible');},add(name){this.classes.add(name);},remove(name){this.classes.delete(name);}},hasAttribute:()=>false});
  const nodes = Array.from({length:6},(_,i)=>makeNode(i));
  class Observer {constructor(fn){callback=fn;observerCount++;}observe(node){observed.push(node);}unobserve(node){unobserved.push(node);}}
  const document={documentElement:{classList:{add(name){assert.equal(name,'motion-enabled');enabled=true;}}},querySelectorAll:()=>nodes};
  const window={matchMedia:()=>({matches:reduced}),requestAnimationFrame:(fn)=>frames.push(fn)};
  if (supportsObserver) window.IntersectionObserver=Observer;
  const nextFrame = () => frames.splice(0).forEach(fn=>fn());
  const context=vm.createContext({window,document,IntersectionObserver:Observer});
  vm.runInContext(engine,context);vm.runInContext(app,context);
  assert.equal(enabled,true,'Initialisation commune effectivement exécutée.');
  if(reduced || !supportsObserver){assert.equal(observed.length,0);assert.ok(nodes.every(node=>node.classList.visible));assert.equal(frames.length,0);}
  else {
    assert.equal(observed.length,0);assert.ok(nodes.every(node=>node.classList.classes.has('is-motion-preparing')));
    nextFrame();assert.equal(observed.length,0);assert.ok(nodes.every(node=>!node.classList.visible),'Un frame intermédiaire conserve réellement l’état caché.');
    nextFrame();assert.equal(observed.length,6);assert.ok(nodes.every(node=>!node.classList.classes.has('is-motion-preparing')));
    callback(nodes.map(target=>({target,isIntersecting:true})));
    assert.equal(unobserved.length,6);assert.ok(nodes.every(node=>!node.classList.visible),'Révélation différée, pas synchrone.');
    nextFrame();assert.ok(nodes.every(node=>node.classList.visible));assert.equal(nodes[2].style.delay,'80ms');
    callback(nodes.map(target=>({target,isIntersecting:false})));assert.ok(nodes.every(node=>node.classList.visible),'Sortie : aucune disparition ni nouveau mouvement.');
    window.MotionSystem.refresh();window.MotionSystem.init();assert.equal(frames.length,0);assert.equal(observed.length,6);assert.equal(observerCount,1,'Initialisation répétée sans observer concurrent.');
    const inserted=makeNode(6);nodes.push(inserted);window.MotionSystem.refresh();window.MotionSystem.refresh();
    nextFrame();assert.equal(observed.length,6);nextFrame();assert.equal(observed.length,7,'Injection dynamique : même préparation, une seule inscription.');
    callback([{target:inserted,isIntersecting:true}]);nextFrame();assert.equal(inserted.classList.visible,true);
  }
}
assert.match(motion,/\.motion-enabled \[data-motion\]\.is-motion-preparing\s*\{\s*transition: none !important;/);
const reducedCss=motion.slice(motion.indexOf('@media (prefers-reduced-motion: reduce)'));
assert.match(reducedCss,/\[data-motion\]\s*\{\s*opacity: 1 !important;\s*transform: none !important;/);
assert.match(checkoutCss,/\.payment-method__icon--logo img\s*\{[^}]*transform: none !important; transition: none !important; animation: none !important/);
assert.match(checkoutCss,/\.payment-method\[aria-disabled="true"\] \{ transform: none !important/);
console.log('CHECKOUT_MICRO_MOTION_OK (frames distinctes, préparation sans disparition animée, apparition unique, refresh dynamique, reduced-motion, fallback sans observer).');
