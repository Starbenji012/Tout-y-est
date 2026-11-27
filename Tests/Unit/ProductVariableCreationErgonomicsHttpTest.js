// Matrice ergonomique réelle, exclusivement dans une copie + BDD/stockage jetables.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const {spawn, execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const sourceRoot = path.resolve(__dirname, '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tye-variants-ergo-'));
const root = path.join(temp, 'site');
const name = 'tout_y_est_photo_http_' + crypto.randomBytes(8).toString('hex');
const php = process.env.PHP_BINARY || 'C:/php/php.exe';
const port = 19000 + crypto.randomInt(500);
const origin = `http://127.0.0.1:${port}`;
const fixture = path.join(root, 'Tests/Fixtures/ProductPhotoHttpFixture.php');
let browser, server, ready = false;
let serverLog = '';
const results = [], errors = [];
const smoke = process.argv.includes('--smoke');
// Reproduce the reported boundary without changing the normal full matrix.
const focused = process.argv.includes('--case=100-768-native');
console.log('ISOLATED_AUDIT_ROOT ' + temp);
// Copie immuable des sources ; aucun upload ne vise Public/uploads du projet.
for (const directory of ['App', 'Config', 'Routes', 'Public', 'vendor']) {
  const source = path.join(sourceRoot, directory);
  if (fs.existsSync(source)) fs.cpSync(source, path.join(root, directory), {recursive:true, filter:file => !file.startsWith(path.join(sourceRoot, 'Public/uploads'))});
}
fs.mkdirSync(path.join(root, 'Database'), {recursive:true});
fs.copyFileSync(path.join(sourceRoot, 'Database/schema.sql'), path.join(root, 'Database/schema.sql'));
fs.mkdirSync(path.join(root, 'Tests/Fixtures'), {recursive:true});
for (const file of ['ProductPhotoHttpFixture.php', 'ProductPhotoHttpRouter.php']) fs.copyFileSync(path.join(sourceRoot, 'Tests/Fixtures', file), path.join(root, 'Tests/Fixtures', file));
fs.mkdirSync(path.join(root, 'Public/uploads/products'), {recursive:true});
fs.mkdirSync(path.join(root, 'Storage'), {recursive:true});
(async () => {
  try {
    execFileSync(php, [fixture, 'setup', name, path.join(temp, 'source.png')], {cwd:root}); ready = true;
    server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'Public', 'Tests/Fixtures/ProductPhotoHttpRouter.php'], {cwd:root, env:{...process.env, DB_DATABASE:name, TYE_PHOTO_TEST_SESSION:temp}, windowsHide:true, stdio:['ignore','ignore','pipe']});
    server.stderr.on('data', data => { serverLog = (serverLog + data.toString()).slice(-16000); });
    browser = await chromium.launch({channel:'chrome', headless:true});
    for (const javaScriptEnabled of focused ? [false] : [true, false]) {
      const context = await browser.newContext({javaScriptEnabled});
      for (const width of focused ? [768] : [375, 768, 1440]) for (const n of focused ? [100] : smoke ? [4] : [4, 12, 30, 100]) {
        const page = await context.newPage({viewport:{width,height:900}});
        await page.setViewportSize({width,height:900});
        page.on('pageerror', e => errors.push(e.message));
        const start = Date.now();
        await page.goto(origin + '/admin/produit/ajouter');
        await page.locator('#product-name').fill(`TEST ERGO ${n} ${width} JS=${javaScriptEnabled}`);
        if (javaScriptEnabled) { await page.locator('#product-category-trigger').click(); await page.locator('#product-category-option-1').click(); }
        else await page.locator('#product-category').selectOption('1');
        await page.locator('#product-status').selectOption('brouillon');
        await page.locator('[name=product_type][value=variable]').check();
        if (!javaScriptEnabled) await Promise.all([
          page.waitForEvent('domcontentloaded'),
          page.getByRole('button', {name:'Appliquer le type de produit'}).click(),
        ]);
        await page.locator('[name="creation_options[0][nom]"]').fill('Couleur');
        await page.locator('[name="creation_options[0][valeurs]"]').fill('Noir\nBlanc');
        const option = page.locator('[name="creation_options[1][nom]"]');
        await option.locator('xpath=ancestor::details[1]/summary').click();
        await option.fill('Taille');
        await page.locator('[name="creation_options[1][valeurs]"]').fill(Array.from({length:n/2}, (_,i) => 'T' + (i+1)).join('\n'));
        // count() is an immediate snapshot, not a retrying assertion. Wait for
        // the entire streamed form and its deferred scripts before inspecting it.
        await Promise.all([
          page.waitForEvent('domcontentloaded'),
          page.getByRole('button', {name:'Afficher les versions',exact:true}).click(),
        ]);
        const rows = page.locator('[data-creation-variant]');
        assert.equal(await rows.count(), n);
        assert.equal(await rows.first().locator('[data-creation-selected]').isVisible(), true);
        if (javaScriptEnabled) {
          await page.waitForFunction(() => document.querySelector('[data-creation-price]')?.disabled === true);
          assert.equal(await rows.first().locator('[data-creation-price]').isDisabled(), true);
          const count = await page.locator('.admin-product-form').evaluate(form => {
            const entries = [...new FormData(form).entries()];
            const variants = entries.filter(([key]) => key.startsWith('creation_variants['));
            return {count:variants.length, unique:new Set(variants.map(([key]) => key)).size, total:entries.length};
          });
          assert.equal(count.count, 6*n); assert.equal(count.unique, 6*n); assert.ok(count.total < 1000);
        }
        for (let i=0; i<n; i++) {
          const row = rows.nth(i);
          await row.locator('[data-creation-selected]').check();
          await row.locator('[data-creation-price]').fill('20');
          await row.locator('input[type=number]').fill('3');
        }
        // Une désélection conserve les valeurs, puis une resélection les rend éditables.
        await rows.first().locator('[data-creation-selected]').uncheck();
        if (javaScriptEnabled) assert.equal(await rows.first().locator('[data-creation-price]').isDisabled(), true);
        await rows.first().locator('[data-creation-selected]').check();
        assert.equal(await rows.first().locator('[data-creation-price]').inputValue(), '20');
        if (javaScriptEnabled) assert.match(await page.locator('[data-creation-count]').innerText(), new RegExp(n + ' sélectionnées'));
        if (!javaScriptEnabled && n === 4) {
          await Promise.all([
            page.waitForEvent('domcontentloaded'),
            page.getByRole('button', {name:'Afficher les versions',exact:true}).click(),
          ]);
          assert.match(await page.locator('[data-creation-count]').innerText(), /4 sélectionnées/);
          assert.equal(await rows.first().locator('[data-creation-price]').inputValue(),'20');
          assert.equal(await rows.first().locator('[data-creation-selected]').isChecked(),true);
        }
        const layout = await page.locator('.admin-product-creation__list').evaluate(list => ({viewport:innerWidth, documentWidth:document.documentElement.scrollWidth, documentHeight:document.documentElement.scrollHeight, listHeight:list.clientHeight, listScrollHeight:list.scrollHeight, nestedScroll:['auto','scroll'].includes(getComputedStyle(list).overflowY), tableDisplay:getComputedStyle(list.querySelector('table')).display}));
        assert.equal(layout.documentWidth, width); assert.equal(layout.nestedScroll, false);
        assert.ok(Math.abs(layout.listScrollHeight-layout.listHeight) <= 2);
        assert.equal(layout.tableDisplay, width === 1440 ? 'table' : 'block');
        const screenshot = `variants-${n}-${width}-${javaScriptEnabled?'js':'native'}.png`;
        await page.screenshot({path:path.join(temp,screenshot),fullPage:true});
        const submissionStart = Date.now();
        // Attach both rejection handlers immediately: a response timeout must reach
        // catch/finally even while click() is still waiting for navigation.
        const [response] = await Promise.all([
          page.waitForResponse(r => r.url() === origin + '/admin/produits/enregistrer' && r.request().method()==='POST'),
          page.locator('[data-product-submit]').click(),
        ]);
        assert.equal(response.status(),303,response.status()===303?'Création réussie':await response.text());
        await page.waitForURL(/\/admin\/produit\?id=/);
        const record={n,width,javaScriptEnabled,secondsAutomation:(Date.now()-start)/1000,submissionMs:Date.now()-submissionStart,explicitClicks:n+6,fieldsFilled:2*n+5,measurementNote:'Normal JS path; focus/scroll and extra diagnostic actions excluded from click count',layout,screenshot,created:true};
        results.push(record); console.log(JSON.stringify(record));
        await page.close();
      }
      const simple = await context.newPage();
      await simple.goto(origin+'/admin/produit/ajouter');
      await simple.locator('#product-name').fill('TEST SIMPLE ERGO '+javaScriptEnabled);
      if (javaScriptEnabled) { await simple.locator('#product-category-trigger').click(); await simple.locator('#product-category-option-1').click(); }
      else await simple.locator('#product-category').selectOption('1');
      await simple.locator('#product-status').selectOption('brouillon');
      await simple.locator('#product-price').fill('20');
      await simple.locator('#product-stock').fill('3');
      await simple.locator('[data-product-submit]').click();
      await simple.waitForURL(/\/admin\/produit\?id=/);
      console.log('SIMPLE_CREATION_OK JS='+javaScriptEnabled);
      await context.close();
    }
    assert.deepEqual(errors,[]);
    console.log('PRODUCT_VARIABLE_ERGONOMICS_OK ' + temp);
  } catch (error) { console.error(error); console.error(serverLog); process.exitCode=1; }
  finally {
    fs.writeFileSync(path.join(temp,'measurements.json'),JSON.stringify({results,errors},null,2));
    if (browser) await browser.close();
    if (server) { server.kill(); await new Promise(resolve => setTimeout(resolve,500)); }
    if (ready) execFileSync(php,[fixture,'cleanup',name],{cwd:root});
  }
})();
