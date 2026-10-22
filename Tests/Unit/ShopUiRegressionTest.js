// Exécute l'orchestrateur du catalogue : aucune requête réelle ni modification métier.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {URL, URLSearchParams} = require('node:url');
(async () => {
  let document;
  const node = () => {
    const classes = new Set();
    return {events: {}, attributes: {}, hidden: false, innerHTML: '', value: '', dataset: {}, isConnected: true,
      classList: {add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value), toggle(value, on) {on ? classes.add(value) : classes.delete(value);}},
      addEventListener(type, fn) {this.events[type] = fn;}, setAttribute(name, value) {this.attributes[name] = value;}, removeAttribute(name) {delete this.attributes[name];},
      focus() {this.focused = true;if(document)document.activeElement=this;}, contains(target) {return target===this||(this.members??[]).includes(target);}, querySelector() {return null;}, querySelectorAll() {return [];}, scrollIntoView() {},
    };
  };
  const panel=node(), open=node(), close=node(), overlay=node(), filters=node(), search=node(), sort=node(), results=node(), content=node(), loader=node(), count=node(), reset=node(), contextFilters=node(), grid=node(), list=node(), body=node(), layout=node();
  panel.members=[close,filters];
  grid.dataset.catalogView='grid';list.dataset.catalogView='list';sort.value='newest';
  const category={name:'categories[]',value:'test',checked:false}, offer={name:'statuses[]',value:'promotion',checked:false}, available={name:'availability',value:'',checked:true}, inStock={name:'availability',value:'in-stock',checked:false};
  const inputs=[category,offer,available,inStock];
  inputs.forEach(input=>{input.matches=selector=>selector.includes('[name="'+input.name+'"]');});
  filters.querySelector=selector=>selector==='[data-catalog-filter-reset]'?reset:selector.includes('availability')?available:null;
  filters.querySelectorAll=selector=>selector.includes('checkbox')?[category,offer]:[];
  panel.querySelector=()=>close;contextFilters.querySelectorAll=()=>[];
  const selectors={'[data-catalog-filters-panel]':panel,'[data-catalog-filter-open]':open,'[data-catalog-filters]':filters,'[data-catalog-search]':search,'[data-catalog-sort]':sort,'[data-catalog-results]':results,'[data-catalog-content]':content,'[data-catalog-loader]':loader,'[data-catalog-count]':count,'[data-catalog-context-filters]':contextFilters,'[data-product-catalog]':layout};
  const events={}, requests=[];let resize, enhancedSort;
  const viewport={matches:false,addEventListener:(_,fn)=>{resize=event=>{viewport.matches=event.matches;fn(event);};}};
  document={body,activeElement:open,querySelector:selector=>selectors[selector]??null,querySelectorAll:selector=>selector.includes('filter-close')?[close,overlay]:[grid,list],addEventListener:(type,fn)=>{events[type]=fn;}};
  class FormData extends Map {constructor(form){super(form===filters?inputs.filter(input=>input.checked).map(input=>[input.name,input.value]):[]);}}
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../Public/assets/js/shop.js'),'utf8'),{
    FormData, URLSearchParams, AbortController, document,
    localStorage:{getItem:()=>null,setItem(){}},window:{SelectControl:{enhance:select=>{enhancedSort=select;}},matchMedia:()=>viewport,history:{replaceState(){}},MotionSystem:{refresh(){},fire(){}}},
    fetch:async url=>{requests.push(new URL(url,'http://local'));return {ok:true,json:async()=>({html:'<div class="product-section__grid">TEST</div>',count:3,facetsHtml:''})};},
  });
  const flush=async()=>{for(let i=0;i<12;i++)await Promise.resolve();};
  assert.equal(enhancedSort,sort,'Le vrai select de tri est enrichi par le composant commun.');
  content.innerHTML='INITIAL';
  open.events.click();assert.equal(panel.classList.contains('is-open'),true);assert.equal(open.attributes['aria-expanded'],'true');
  overlay.events.click();assert.equal(panel.classList.contains('is-open'),false);assert.equal(content.innerHTML,'INITIAL','Fermer ne remplace ni ne compresse les cartes.');
  open.events.click();events.keydown({key:'Escape'});assert.equal(body.classList.contains('catalog-filters-open'),false);
  open.events.click();resize({matches:true});assert.equal(panel.classList.contains('is-open'),false);
  assert.equal(panel.hidden,false);assert.equal(open.attributes['aria-expanded'],'true');
  close.events.click();assert.equal(panel.hidden,true);assert.equal(layout.classList.contains('is-filters-collapsed'),true);assert.equal(document.activeElement,open);
  open.events.click();assert.equal(panel.hidden,false);assert.equal(layout.classList.contains('is-filters-collapsed'),false);
  assert.equal(panel.attributes['aria-modal'],undefined,'Pas de dialogue modal sur desktop.');
  document.activeElement=close;events.keydown({key:'Escape',defaultPrevented:true});assert.equal(panel.hidden,false,'Escape traité par le select ne ferme pas les filtres.');
  events.keydown({key:'Escape'});assert.equal(panel.hidden,true);
  resize({matches:false});assert.equal(panel.hidden,false);assert.equal(panel.classList.contains('is-open'),false);assert.equal(layout.classList.contains('is-filters-collapsed'),false);
  open.events.click();assert.equal(panel.attributes['aria-modal'],'true');close.events.click();
  category.checked=true;filters.events.change({target:category});await flush();assert.equal(requests.at(-1).searchParams.get('categories[]'),'test');
  offer.checked=true;filters.events.change({target:offer});await flush();assert.equal(requests.at(-1).searchParams.get('statuses[]'),'promotion');
  available.checked=false;inStock.checked=true;filters.events.change({target:inStock});await flush();assert.equal(requests.at(-1).searchParams.get('availability'),'in-stock');
  sort.value='price-asc';sort.events.change();await flush();assert.equal(requests.at(-1).searchParams.get('sort'),'price-asc');assert.equal(requests.at(-1).searchParams.get('categories[]'),'test');
  const html=content.innerHTML;list.events.click();assert.equal(results.classList.contains('is-list-view'),true);grid.events.click();assert.equal(results.classList.contains('is-list-view'),false);assert.equal(content.innerHTML,html);
  assert.equal(loader.hidden,true);assert.equal(results.attributes['aria-busy'],'false');
  const css=fs.readFileSync(path.join(__dirname,'../../Public/assets/css/shop.css'),'utf8');
  const desktop=css.slice(css.indexOf('.catalog-filters {'),css.indexOf('.catalog-filters__header'));
  assert.doesNotMatch(desktop,/max-height:|overflow-y:|position: sticky/);
  assert.match(css,/grid-template-columns: 16rem minmax\(0, 1fr\)/);
  assert.match(css,/\.catalog-layout\.is-filters-collapsed\s*\{ grid-template-columns: minmax\(0, 1fr\)/);
  assert.match(css,/prefers-reduced-motion/);
  assert.match(css,/body\.catalog-filters-open \.product-section--shop\s*\{ z-index: 1020/);
  assert.match(css,/\.catalog-filter-group\s*\{\s*min-inline-size: 0;\s*border: 0;/);
  assert.match(css,/\.catalog-price-range\s*\{\s*display: grid;\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/);
  assert.match(css,/@media \(max-width: 64rem\)/);assert.match(css,/overflow-y: auto/);
  assert.doesNotMatch(fs.readFileSync(path.join(__dirname,'../../Public/assets/js/shop.js'),'utf8'),/rating/);
  console.log('SHOP_UI_REGRESSION_OK (drawer, fermeture, catégories/offres/stock, tri, grille/liste, largeur fluide, scroll mobile, notation absente).');
})();
