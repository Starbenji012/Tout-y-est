const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const {spawn, execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || 'C:/php/php.exe';
const name = 'tout_y_est_photo_http_' + crypto.randomBytes(8).toString('hex');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tye-variable-http-'));
const source = path.join(temp, 'source.png');
const fixture = path.join(root, 'Tests/Fixtures/ProductPhotoHttpFixture.php');
const port = 18600 + crypto.randomInt(300);
const origin = `http://127.0.0.1:${port}`;
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let server, browser, ready = false, logs = '';
(async () => {
  try {
    execFileSync(php, [fixture, 'setup', name, source], {cwd: root}); ready = true;
    server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'Public', 'Tests/Fixtures/ProductPhotoHttpRouter.php'], {cwd: root, env: {...process.env, DB_DATABASE: name, TYE_PHOTO_TEST_SESSION: temp}, windowsHide: true});
    server.stderr.on('data', data => { logs += data; });
    browser = await chromium.launch({channel: 'chrome', headless: true});
    const errors = [];
    let screenshots = 0;
    const nativeSmoke = process.argv.includes('--native-smoke');
    const widths = nativeSmoke ? [375] : [375, 768, 1440];
    for (const javaScriptEnabled of nativeSmoke ? [false] : [true, false]) {
      const context = await browser.newContext({javaScriptEnabled});
      for (const width of widths) {
        const page = await context.newPage(); await page.setViewportSize({width, height: 1000});
        page.on('pageerror', e => errors.push(e.message));
        await page.goto(origin + '/admin/produit/ajouter');
        assert.equal(await page.locator('[name=product_type][value=simple]').isChecked(), true);
        await page.locator('#product-name').fill(`TEST Variable HTTP ${width} JS=${javaScriptEnabled}`);
        await page.locator('#product-status').selectOption('brouillon');
        await page.locator('#product-category').selectOption('1', {force: true});
        await page.locator('[name=product_type][value=variable]').check();
        if (!javaScriptEnabled) await page.getByRole('button', {name: 'Appliquer le type de produit'}).click();
        assert.equal(await page.locator('[name="creation_options[1][nom]"]').isVisible(), false);
        assert.equal(await page.locator('[data-creation-option]').nth(2).isVisible(), false);
        await page.locator('[name="creation_options[0][nom]"]').fill('Couleur');
        await page.locator('[name="creation_options[0][valeurs]"]').fill('Noir\nBlanc');
        await page.locator('[data-creation-option]').nth(1).locator('summary').click();
        await page.locator('[name="creation_options[1][nom]"]').fill('Taille');
        await page.locator('[name="creation_options[1][valeurs]"]').fill('S');
        assert.equal(await page.locator('[data-creation-option]').nth(2).locator('summary').isVisible(),true);
        await page.locator('[data-creation-option]').nth(2).locator('summary').click();
        await page.locator('[name="creation_options[2][nom]"]').fill('Matière');
        await page.locator('[name="creation_options[2][valeurs]"]').fill('Coton');
        assert.equal(await page.locator('[data-creation-option]').count(),3);
        assert.equal(await page.locator('.admin-product-creation__add-label:visible').count(),0);
        await page.getByRole('button', {name: 'Afficher les versions'}).click();
        await page.waitForSelector('[data-creation-variant]');
        assert.equal(await page.locator('#product-name').inputValue(), `TEST Variable HTTP ${width} JS=${javaScriptEnabled}`);
        const rows = page.locator('[data-creation-variant]'); assert.equal(await rows.count(), 2);
        const optionLayout = await page.locator('.admin-product-creation__option-fields').first().evaluate(group => {
          const labels = [...group.querySelectorAll('label')].map(label => label.getBoundingClientRect());
          return {sideBySide:Math.abs(labels[0].top-labels[1].top)<2,stacked:labels[1].top>labels[0].top+labels[0].height};
        });
        assert.equal(optionLayout.sideBySide, width >= 768);
        assert.equal(optionLayout.stacked, width < 768);
        assert.equal(await rows.first().locator('[data-creation-selected]').isVisible(), true);
        if (javaScriptEnabled) {
          await page.waitForFunction(() => document.querySelector('[data-creation-price]')?.disabled === true);
          assert.equal(await rows.first().locator('[data-creation-price]').isDisabled(), true);
          const fields = await page.locator('.admin-product-form').evaluate(form => [...new FormData(form).keys()].filter(key => key.startsWith('creation_variants[')));
          assert.equal(fields.length, 12, 'Six valeurs par ligne non sélectionnée, sans doublon');
          assert.equal(new Set(fields).size, 12);
        }
        await rows.first().locator('[data-creation-selected]').check();
        await rows.first().locator('[data-creation-price]').fill('12.50');
        assert.equal(await rows.first().locator('input[type=number]').inputValue(), '0');
        await rows.first().locator('[data-creation-advanced] > summary').click();
        await rows.first().locator('[data-creation-sku-mode]').selectOption('manual');
        await rows.first().locator('[data-creation-sku-manual] input').fill(`HTTP-${width}-${javaScriptEnabled ? 'JS' : 'NATIVE'}`);
        // Shared photos are prepared through the same native/enhanced workflow.
        await page.locator('#creation-photo').setInputFiles(source);
        if (!javaScriptEnabled) await page.getByRole('button', {name: 'Préparer la photo sélectionnée'}).click();
        await page.waitForSelector('[data-prepared-list] input', {state:'attached'});
        if (javaScriptEnabled) await page.waitForFunction(() => document.querySelector('[data-prepared-list] p')?.textContent.includes('Photo prête'));
        // Server validation error preserves selection, manual SKU, stock and photo tickets.
        await page.locator('#product-name').fill('');
        if (javaScriptEnabled) {
          await page.evaluate(() => { document.querySelector('.admin-product-form').noValidate = true; });
        }
        if (!javaScriptEnabled) {
          const native = new URLSearchParams();
          for (const control of await page.locator('.admin-product-form input,.admin-product-form select,.admin-product-form textarea').all()) {
            const key = await control.getAttribute('name');
            const type = await control.getAttribute('type');
            if (!key || await control.isDisabled() || type === 'file' || (['radio','checkbox'].includes(type) && !await control.isChecked())) continue;
            native.append(key, await control.inputValue());
          }
          const response = await context.request.post(origin + '/admin/produits/enregistrer', {data: native.toString(), headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
          assert.equal(response.status(), 422);
          const html = await response.text(); assert.match(html, new RegExp(`HTTP-${width}-NATIVE`)); assert.match(html, /name="photo_ids\[\]"/);
          assert.equal((html.match(/data-creation-option open/g)||[]).length,3,'Les trois options renseignées restent ouvertes après erreur');
          const invalidOptions = new URLSearchParams(native);
          invalidOptions.set('nom','TEST Option errors'); invalidOptions.set('creation_options[0][nom]','');
          invalidOptions.set('creation_options[2][nom]','');
          const invalidResponse = await context.request.post(origin + '/admin/produits/enregistrer', {data: invalidOptions.toString(), headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
          assert.equal(invalidResponse.status(),422);
          const invalidHtml = await invalidResponse.text(); assert.match(invalidHtml,new RegExp(`HTTP-${width}-NATIVE`)); assert.match(invalidHtml,/Variante précédente/);
          assert.equal((invalidHtml.match(/data-creation-option open/g)||[]).length,3,'Une option avec des valeurs mais sans nom reste visible');
          const stale = new URLSearchParams(native); stale.set('nom','TEST Stale options'); stale.set('creation_options[0][valeurs]','Vert\nBlanc');
          const staleResponse = await context.request.post(origin + '/admin/produits/enregistrer', {data: stale.toString(), headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
          assert.equal(staleResponse.status(),422);
          const staleHtml = await staleResponse.text(); assert.match(staleHtml,/Les options ont changé/);
          assert.equal(staleHtml.match(/name="creation_signature" value="([a-f0-9]{64})"/)[1],native.get('creation_signature'),'Stale form cannot silently become valid after an error');
          await page.locator('#product-name').fill('TEST Restored native');
        } else {
          await page.locator('[data-product-submit]').click();
          await page.waitForSelector('.admin-product-notice[role=alert]');
          assert.equal(await page.locator('[data-creation-selected]').first().isChecked(), true);
          assert.equal(await page.locator('[data-creation-sku-manual] input').first().inputValue(), `HTTP-${width}-JS`);
          assert.equal(await page.locator('[data-prepared-list] input').count(), 1);
          assert.equal(await page.locator('[name="creation_options[2][nom]"]').inputValue(),'Matière');
          assert.equal(await page.locator('[name="creation_options[2][valeurs]"]').inputValue(),'Coton');
          assert.equal(await page.locator('[name="creation_options[2][nom]"]').isVisible(),true);
          await page.locator('#product-name').fill('TEST Restored JS');
        }
        await page.locator('#product-status').selectOption('actif');
        // In no-JS mode all native controls remain available inside the disclosure.
        await page.locator('[data-creation-details] > summary').click();
        await page.locator('[data-creation-details] > summary').click();
        if (javaScriptEnabled) assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `No overflow at ${width}`);
        else {
          const shot = await page.screenshot({path: path.join(temp, `native-${width}.png`)}); assert.ok(shot.length > 0);
        }
        await page.screenshot({path: path.join(temp, `variable-${width}-${javaScriptEnabled}.png`)}); screenshots++;
        await page.locator('[data-product-submit]').click();
        await page.waitForURL(/\/admin\/produit\?id=/);
        assert.match(await page.locator('.admin-product-notice--success').innerText(), /indisponible aux clients/);
        assert.equal(await page.locator('[data-simple-creation]').count(), 0);
        await page.close();
      }
      // A genuine HTTP parser overflow must never create a product.
      const session = await (await context.request.get(origin + '/__fixture')).json();
      if (javaScriptEnabled) {
        const base = {_token: session.csrf, product_type: 'variable', nom: 'TEST 100 HTTP', id_categorie: '1', statut: 'brouillon', sku_request: crypto.randomBytes(16).toString('hex'), 'creation_options[0][nom]': 'Numéro', 'creation_options[0][valeurs]': Array.from({length:100}, (_,i)=>String(i+1)).join('\n'), creation_end: 'v1'};
        const preview = await context.request.post(origin + '/admin/produits/enregistrer', {form: {...base, creation_action: 'preview'}});
        assert.equal(preview.status(), 200);
        const html = await preview.text();
        base.creation_signature = html.match(/name="creation_signature" value="([a-f0-9]{64})"/)[1];
        base.creation_preview = '1';
        for (let i=1;i<=100;i++) for (const [key,value] of Object.entries({selected:'1',prix:'1',devise:'USD',stock:'0',statut:'actif',sku_mode:'auto',sku:''})) base[`creation_variants[1:${i}][${key}]`] = value;
        // Keep the end marker last, exactly as in the native rendered form.
        delete base.creation_end; base.creation_end = 'v1';
        assert.ok(Object.keys(base).length < 800);
        const created = await context.request.post(origin + '/admin/produits/enregistrer', {form: base, maxRedirects:0});
        assert.equal(created.status(), 303, await created.text());
        const replay = await context.request.post(origin + '/admin/produits/enregistrer', {form: base, maxRedirects:0});
        assert.equal(replay.headers().location, created.headers().location);
      }
      const beforeTruncation = await (await context.request.get(origin + '/__fixture')).json();
      const truncated = {_token: session.csrf, product_type: 'simple', nom: 'TEST TRUNCATED', id_categorie: '1', statut: 'brouillon', prix: '1', devise: 'USD', stock_initial: '0'};
      for (let i = 0; i < 1050; i++) truncated['overflow_' + i] = 'x';
      truncated.creation_end = 'v1';
      const response = await context.request.post(origin + '/admin/produits/enregistrer', {form: truncated});
      assert.equal(response.status(), 422); assert.match(await response.text(), /tronqué/);
      const after = await (await context.request.get(origin + '/__fixture')).json(); assert.equal(after.products, beforeTruncation.products);
      await context.close();
    }
    assert.deepEqual(errors, []);
    console.log(`PRODUCT_VARIABLE_CREATION_HTTP_OK (${screenshots} JS/native renders, ${widths.join('/')} px, selection, manual SKU, photos, validation recovery, publication guard message, real max_input_vars truncation). Captures: ${temp}`);
  } catch (e) { console.error(e); console.error(logs.slice(-3500)); process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    if (server) { server.kill(); await pause(300); }
    if (ready) execFileSync(php, [fixture, 'cleanup', name], {cwd: root});
  }
})();
