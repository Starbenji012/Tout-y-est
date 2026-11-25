const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const {spawn, execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const name = 'tout_y_est_photo_http_' + crypto.randomBytes(8).toString('hex');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tye-photo-http-'));
const source = path.join(temp, 'source.png');
const screenshots = fs.mkdtempSync(path.join(os.tmpdir(), 'tye-photo-ui-'));
const php = process.env.PHP_BINARY || 'C:/php/php.exe';
const port = 18100 + crypto.randomInt(500);
const origin = `http://127.0.0.1:${port}`;
const fixture = path.join(root, 'Tests/Fixtures/ProductPhotoHttpFixture.php');
const ticket = () => crypto.randomBytes(16).toString('hex');
let server, browser, ready = false, logs = '';
const timeout = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
  try {
    execFileSync(php, [fixture, 'setup', name, source], {cwd: root}); ready = true;
    server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'Public', 'Tests/Fixtures/ProductPhotoHttpRouter.php'], {cwd: root, env: {...process.env, DB_DATABASE: name, TYE_PHOTO_TEST_SESSION: temp}, windowsHide: true});
    server.stderr.on('data', data => { logs += data; });
    for(let i=0;i<100;i++) { try { const response = await fetch(origin + '/__fixture'); if(response.ok) break; } catch {} await timeout(100); }
    browser = await chromium.launch({channel: 'chrome', headless: true});
    const context = await browser.newContext();
    const request = context.request;
    const session = await (await request.get(origin + '/__fixture')).json();
    const png = fs.readFileSync(source);
    const upload = async (batch, id, buffer = png, csrf = session.csrf) => request.post(origin + '/admin/produits/photos/preparer', {multipart: {_token: csrf, batch, ticket: id, photo: {name: 'test.png', mimeType: 'image/png', buffer}}});
    const batch = ticket(), id = ticket();
    assert.equal((await upload(batch, id, png, 'invalid')).status(), 419);
    assert.equal((await upload(ticket(), ticket(), Buffer.from('not an image'))).status(), 422);
    const tenMio = Buffer.alloc(10 * 1024 * 1024); png.copy(tenMio);
    const large = await upload(batch, id, tenMio); assert.equal(large.status(), 200, await large.text());
    assert.equal((await upload(batch, id, tenMio)).status(), 200, 'Reprise 10 Mio sans doublon');
    assert.equal((await upload(ticket(), ticket(), Buffer.alloc(11*1024*1024))).status(), 422, 'Limite fichier PHP détectée');
    assert.equal((await upload(ticket(), ticket(), Buffer.alloc(13*1024*1024))).status(), 413, 'POST trop gros identifié avant CSRF');
    const image = await request.get(origin + `/admin/produits/photos/apercu?batch=${batch}&ticket=${id}`);
    assert.equal(image.status(), 200); assert.equal(image.headers()['content-type'], 'image/webp');
    const fill = async page => {
      await page.locator('#product-name').fill('TEST Publication HTTP');
      await page.locator('#product-price').fill('15.00');
      // Le select natif reste la source de vérité derrière le composant commun.
      await page.locator('#product-category').selectOption({value:'1'}, {force:true});
    };
    for(const width of [375,768,1440]) {
      const page = await context.newPage(); await page.setViewportSize({width,height:1000});
      const errors=[]; page.on('pageerror', e=>errors.push(e.message));
      await page.goto(origin + '/admin/produit/ajouter'); await fill(page);
      assert.equal(await page.locator('#product-status').inputValue(), 'actif');
      assert.equal(await page.locator('[data-product-submit]').innerText(), 'Publier le produit');
      await page.locator('#product-status').selectOption('brouillon');
      assert.equal(await page.locator('[data-product-submit]').innerText(), 'Créer le brouillon');
      await page.locator('#product-status').selectOption('actif');
      // Une réponse perdue après traitement ne crée pas une seconde préparation.
      let lose = true;
      await page.route('**/admin/produits/photos/preparer', async route => {
        if (lose) {
          lose=false;
          // Chromium ne fournit pas les octets File dans request.postDataBuffer().
          // Reproduire le traitement abouti avec le même ticket et les vrais octets.
          const pageBatch=await page.locator('[name=photo_batch]').inputValue();
          const pageTicket=await page.locator('[data-prepared-list] li input').inputValue();
          const response=await upload(pageBatch,pageTicket,png);
          if(response.status()!==200)errors.push('Injection réseau : '+await response.text());
          await route.abort('failed');
        }
        else { await timeout(600); await route.continue(); }
      });
      await page.locator('#creation-photo').setInputFiles(source);
      await page.getByRole('button',{name:'Réessayer',exact:true}).waitFor();
      await page.getByRole('button',{name:'Réessayer',exact:true}).click();
      await page.locator('[data-product-submit]').click();
      assert.match(await page.locator('[data-photo-feedback]').innerText(), /Attendez/);
      try{await page.waitForFunction(()=>document.querySelector('[data-prepared-list] li p')?.textContent.includes('Photo prête'));}
      catch(error){throw new Error(error.message+' PANEL: '+await page.locator('[data-photo-preparation]').innerText());}
      await page.locator('#creation-photo').setInputFiles(source);
      await page.waitForFunction(()=>document.querySelector('[data-photo-feedback]').textContent.includes('déjà ajoutée'));
      assert.equal(await page.locator('[data-prepared-list] li').count(), 1);
      const files = [1,2,3,4,5].map(i=>({name:`photo-${i}.png`,mimeType:'image/png',buffer:Buffer.concat([png,Buffer.from([i])])}));
      await page.locator('#creation-photo').setInputFiles(files);
      await page.waitForFunction(()=>document.querySelectorAll('[data-prepared-list] li').length===6 && [...document.querySelectorAll('[data-prepared-list] input')].every(el=>!el.disabled));
      const rows = page.locator('[data-prepared-list] li');
      await rows.nth(1).getByRole('button',{name:'Définir comme principale'}).click();
      const chosen = await page.locator('[name=primary_photo]').inputValue();
      await rows.nth(1).getByRole('button',{name:'Monter',exact:true}).click();
      assert.equal(await rows.first().locator('input').inputValue(), chosen);
      await rows.nth(5).getByRole('button',{name:'Retirer',exact:true}).click();
      assert.equal(await rows.count(), 5);
      await page.waitForFunction(()=>document.querySelector('[data-photo-preparation]').getAttribute('aria-busy')==='false');
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`Responsive ${width}`);
      assert.deepEqual(errors,[]);
      await page.screenshot({path:path.join(screenshots,`photos-${width}.png`),fullPage:true});
      if(width===375){
        await page.locator('#product-price').fill('-1');
        await page.locator('[data-product-submit]').click();
        await page.locator('#product-error-prix').waitFor();
        assert.equal(await page.locator('[data-prepared-list] li').count(),5,'Photos conservées après validation serveur');
        await page.locator('#product-price').fill('15');
        await page.locator('[data-product-submit]').click();await page.waitForURL(/\/admin\/produit\?id=/);
        assert.match(await page.locator('.admin-product-notice--success').innerText(),/publié/);
      }
      await page.close();
    }
    // Débit d'envoi réellement réduit dans Chromium, sous la limite HTTP de 60 s.
    const slow = await context.newPage();await slow.goto(origin+'/admin/produit/ajouter');await fill(slow);
    const cdp=await context.newCDPSession(slow);await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:200,downloadThroughput:2*1024*1024,uploadThroughput:256*1024});
    const slowBytes=Buffer.alloc(512*1024);png.copy(slowBytes);
    await slow.locator('#creation-photo').setInputFiles({name:'slow.png',mimeType:'image/png',buffer:slowBytes});
    await slow.locator('progress:not([hidden])').waitFor();
    await slow.locator('[data-product-submit]').click();assert.match(await slow.locator('[data-photo-feedback]').innerText(),/Attendez/);
    await slow.waitForFunction(()=>document.querySelector('[data-prepared-list] li p')?.textContent.includes('Photo prête'));
    await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});await slow.close();
    // Parcours natif : préparer une photo sans valider les champs du produit.
    const native = await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:1000}});
    const page = await native.newPage();await page.goto(origin+'/admin/produit/ajouter');
    await page.locator('#creation-photo').setInputFiles(source);
    await page.getByRole('button',{name:'Préparer la photo sélectionnée'}).click();
    await page.locator('[data-prepared-list] img').waitFor();
    await fill(page); await page.locator('[data-product-submit]').press('Enter');await page.waitForURL(/\/admin\/produit\?id=/);
    await native.close();
    // Deux requêtes HTTP identiques : une création et un résultat commun.
    const b=ticket(), photo=ticket();assert.equal((await upload(b,photo)).status(),200);
    const payload={_token:session.csrf,nom:'TEST Concurrent HTTP',description:'',id_categorie:'1',sku_mode:'auto',prix:'10',devise:'USD',stock_initial:'0',statut:'actif',sku_request:ticket(),photo_batch:b,'photo_ids[]':photo,primary_photo:photo};
    const responses=await Promise.all([1,2].map(()=>request.post(origin+'/admin/produits/enregistrer',{form:payload,maxRedirects:0})));
    assert.deepEqual(responses.map(r=>r.status()),[303,303]);assert.equal(responses[0].headers().location,responses[1].headers().location);
    const after=await(await request.get(origin+'/__fixture')).json();assert.equal(after.products,3);assert.equal(after.images,7);
    await context.close();
    console.log('PRODUCT_PHOTO_HTTP_OK (HTTP 10 Mio, 413/422/CSRF, réponse perdue/reprise, débit réduit/progression, 6 photos/ordre/principale/retrait, publication/validation, 375/768/1440, sans JS, concurrence). Captures : '+screenshots);
  } finally {
    if(browser) await browser.close();
    if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}
    if(ready)execFileSync(php,[fixture,'cleanup',name],{cwd:root});
    fs.rmSync(temp,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);console.error(logs.slice(-2500));process.exitCode=1;});
