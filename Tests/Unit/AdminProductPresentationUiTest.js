// Vrais rendus PHP + navigateur, sans connexion à une base ni écriture métier.
// npm install playwright dans l'environnement de test, ou PLAYWRIGHT_MODULE vers son module.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const php = String.raw`
$root=$argv[1];$scenario=$argv[2];
$product=str_starts_with($scenario,'create')?null:['id_produit'=>1,'nom'=>'Produit de démonstration','description'=>'Description de démonstration','id_categorie'=>1,'statut'=>'brouillon'];
$categories=[['id_categorie'=>1,'nom'=>'Accessoires']];$canPublish=false;$photos=[];$flash=null;$formError=null;$fieldErrors=[];$old=['sku_request'=>str_repeat('a',32)];$csrfToken='fixture';
$variants=[['id_variante'=>1,'sku'=>'TYE-P000001','description_variante'=>'Noir / Petit','prix_reference'=>'10.00','priceLabel'=>'$10.00','devise_reference'=>'USD','stock'=>4,'statut'=>'actif','active'=>true,'statusLabel'=>'Active']];
$salesSummary=['count'=>1,'activeCount'=>1,'stock'=>4,'priceLabel'=>'$10.00'];
if($scenario==='create-manual-error'){$old+=['sku_mode'=>'manual','sku'=>'MA-REFERENCE','prix'=>'-1'];$formError='Corrigez le prix.';$fieldErrors=['prix'=>'Le prix ne peut pas être négatif.'];}
if($scenario==='create-empty-manual'){$old+=['sku_mode'=>'manual','sku'=>''];$formError='Référence requise.';$fieldErrors=['sku'=>'Saisissez votre référence.'];}
if($scenario==='create-auto-error'){$old+=['sku_mode'=>'auto','prix'=>'-1'];$formError='Corrigez le prix.';$fieldErrors=['prix'=>'Le prix ne peut pas être négatif.'];}
if(in_array($scenario,['multiple','large'],true)){
    $variants[]=['id_variante'=>2,'sku'=>'FOURNISSEUR-REFERENCE-TRES-LONGUE-'.str_repeat('X',60),'description_variante'=>'Noir / Grand','prix_reference'=>'20.00','priceLabel'=>'$20.00','devise_reference'=>'USD','stock'=>7,'statut'=>'active','active'=>true,'statusLabel'=>'Active'];
    $variants[]=['id_variante'=>3,'sku'=>'ANCIEN-SKU-CONSERVE','description_variante'=>'Blanc / Grand','prix_reference'=>'999.00','priceLabel'=>'$999.00','devise_reference'=>'USD','stock'=>99,'statut'=>'inactif','active'=>false,'statusLabel'=>'Inactive'];
    if($scenario==='large')for($i=4;$i<=100;$i++)$variants[]=array_replace($variants[2],['id_variante'=>$i,'sku'=>'ANCIEN-'.$i,'description_variante'=>'Combinaison '.$i]);
    $salesSummary=['count'=>count($variants),'activeCount'=>2,'stock'=>11,'priceLabel'=>'$10.00 – $20.00'];
    $versions=['available'=>true,'definitions'=>[['id_option'=>1,'nom'=>'Couleur','position'=>1,'valeurs'=>[['id_valeur'=>1,'libelle'=>'Noir']]]],'variants'=>$variants,'candidateError'=>null,'candidates'=>[]];
}
if($scenario==='empty'){$variants=[];$salesSummary=['count'=>0,'activeCount'=>0,'stock'=>0,'priceLabel'=>'Aucune variante active'];}
require $root.'/App/views/admin/product-form.php';
`;

(async () => {
  const browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome', headless: true});
  const screenshots = fs.mkdtempSync(path.join(os.tmpdir(), 'tye-presentation-'));
  let checks = 0;
  try {
    for (const width of [375, 768, 1440]) for (const scenario of ['create', 'create-manual-error', 'create-empty-manual', 'create-auto-error', 'simple', 'multiple', 'large', 'empty']) {
      const page = await browser.newPage({viewport: {width, height: 1000}});
      const errors = []; page.on('pageerror', error => errors.push(error.message));
      const html = execFileSync('php', ['-r', php, root, scenario], {encoding: 'utf8'});
      await page.setContent('<!doctype html><html lang="fr"><meta charset="utf-8"><body class="admin-body"><main class="admin-main">' + html + '</main></body></html>');
      for (const name of ['variables', 'reset', 'global', 'animations', 'utilities', 'admin', 'admin-products', 'select-control', 'ui-components']) await page.addStyleTag({path: path.join(root, 'Public/assets/css', name + '.css')});
      for (const name of ['select-control', 'admin-product-form', 'admin-product-options']) await page.addScriptTag({path: path.join(root, 'Public/assets/js', name + '.js')});
      await page.evaluate(() => { window.testSubmits = 0; document.querySelector('.admin-product-form').addEventListener('submit', e => { window.testSubmits++; e.preventDefault(); }); });
      if (scenario.startsWith('create')) {
        const sku = page.locator('#product-sku');
        const manual = page.locator('[name=sku_mode][value=manual]');
        const auto = page.locator('[name=sku_mode][value=auto]');
        const initiallyManual = scenario.includes('manual');
        assert.equal(await manual.isChecked(), initiallyManual);
        assert.equal(await sku.isVisible(), initiallyManual);
        assert.equal(await sku.evaluate(el => el.required), initiallyManual);
        assert.equal(await sku.isDisabled(), !initiallyManual);
        if (scenario === 'create-manual-error') assert.equal(await sku.inputValue(), 'MA-REFERENCE');
        if (scenario === 'create-empty-manual') {
          assert.equal(await sku.getAttribute('aria-invalid'), 'true');
          assert.equal(await sku.evaluate(el => el.checkValidity()), false);
        }
        await auto.check();
        assert.equal(await page.locator('[data-sku-auto-help]').innerText(), "La référence sera générée lors de l'enregistrement du produit.");
        assert.equal(await page.locator('form.admin-product-form').evaluate(el => new FormData(el).has('sku')), false);
        await auto.focus(); await page.keyboard.press('ArrowRight');
        assert.equal(await manual.isChecked(), true);
        assert.equal(await sku.evaluate(el => el.required), true);
        await sku.fill('REFERENCE-GARDEE');
        await auto.check(); assert.equal(await sku.isVisible(), false);
        await manual.check(); assert.equal(await sku.inputValue(), 'REFERENCE-GARDEE');
        assert.equal(await page.locator('[name=sku_request]').first().inputValue(), 'a'.repeat(32));
      } else if (['multiple', 'large'].includes(scenario)) {
        const disclosure = page.locator('#product-sales-details');
        const summary = disclosure.locator('summary');
        assert.equal(await disclosure.evaluate(el => el.open), false);
        assert.equal(await page.locator('table').count(), 1, 'Un seul exemplaire des détails');
        assert.equal(await page.locator('table').isVisible(), false);
        assert.ok((await page.locator('.admin-product-sales__summary').innerText()).includes('11 unités'));
        await page.getByRole('button', {name: /Voir plus/}).click();
        assert.equal(await disclosure.evaluate(el => el.open), true);
        assert.ok((await summary.innerText()).includes('Voir moins'));
        assert.equal(await page.locator('tbody tr').count(), scenario === 'large' ? 100 : 3);
        await summary.focus(); await page.keyboard.press('Space');
        assert.equal(await disclosure.evaluate(el => el.open), false);
        await page.keyboard.press('Enter'); assert.equal(await disclosure.evaluate(el => el.open), true);
        assert.equal(await page.locator('.admin-product-sales__variants').evaluate(el => el.clientHeight <= 514), true, 'Hauteur bornée des grandes listes');
        const region = disclosure.locator('[role="region"]');
        await region.focus();
        await page.keyboard.press('Escape');
        assert.equal(await disclosure.evaluate(el => el.open), false);
        assert.equal(await summary.evaluate(el => el === document.activeElement), true);
        assert.equal(await summary.getAttribute('aria-expanded'), 'false');
        await summary.click();
        await region.click({position: {x: 8, y: 8}});
        assert.equal(await disclosure.evaluate(el => el.open), true, 'Le clic interne conserve les détails');
        await region.evaluate(el => { el.scrollTop = el.scrollHeight; });
        assert.equal(await disclosure.evaluate(el => el.open), true, 'Le défilement conserve les détails');
        await summary.click();
        assert.equal(await disclosure.evaluate(el => el.open), false, 'Voir moins ferme toujours les détails');
        await summary.click();
        await page.locator('#product-name').click();
        assert.equal(await disclosure.evaluate(el => el.open), false, 'Le clic extérieur ferme les détails');
        assert.equal(await page.locator('#product-name').evaluate(el => el === document.activeElement), true, 'Le focus extérieur est préservé');
      } else {
        assert.equal(await page.locator('#product-sales-details').count(), 0);
        if (scenario === 'simple') assert.ok((await page.locator('.admin-product-sales').innerText()).includes('TYE-P000001'));
        else assert.ok((await page.locator('.admin-product-sales').innerText()).includes('Aucune information de vente'));
      }
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${scenario} ${width}: débordement`);
      assert.equal(await page.evaluate(() => window.testSubmits), 0, 'Les interactions de présentation ne soumettent pas le formulaire');
      assert.deepEqual(errors, []);
      if (['create', 'multiple'].includes(scenario)) await page.screenshot({path: path.join(screenshots, `${scenario}-${width}.png`), fullPage: true});
      checks++; await page.close();
    }
    // Sans JS : changement de mode par CSS et détails natifs utilisables au clavier.
    const page = await browser.newPage({viewport: {width: 375, height: 1000}, javaScriptEnabled: false});
    const load = async scenario => {
      const html = execFileSync('php', ['-r', php, root, scenario], {encoding: 'utf8'});
      // addStyleTag attend son événement JS de chargement : injecter les styles dans
      // le document initial pour tester réellement un navigateur sans JavaScript.
      const css = ['variables', 'reset', 'global', 'animations', 'utilities', 'admin', 'admin-products', 'ui-components'].map(name => fs.readFileSync(path.join(root, 'Public/assets/css', name + '.css'), 'utf8')).join('\n');
      await page.setContent('<!doctype html><html lang="fr"><meta charset="utf-8"><style>' + css + '</style><body>' + html + '</body></html>');
    };
    await load('create-empty-manual');
    await page.locator('[value=auto]').check(); assert.equal(await page.locator('#product-sku').isVisible(), false);
    await page.locator('[value=manual]').check(); assert.equal(await page.locator('#product-sku').isVisible(), true);
    await load('multiple'); await page.locator('summary').click(); assert.equal(await page.locator('table').isVisible(), true);
    await page.locator('summary').press('Enter'); assert.equal(await page.locator('table').isVisible(), false);
    await page.close();
    console.log(`ADMIN_PRODUCT_PRESENTATION_UI_OK (${checks} rendus à 375/768/1440, modes SKU/erreurs, détails/clavier, 100 variantes, sans JS). Captures : ${screenshots}`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
