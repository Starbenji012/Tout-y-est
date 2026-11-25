// Le formulaire reste un POST classique, sans conversion monétaire dans le navigateur.
(() => {
  document.querySelectorAll('[data-sales-disclosure]').forEach(details => {
    const summary = details.querySelector('summary');
    const region = details.querySelector('[role="region"]');
    const syncDisclosure = () => {
      summary.setAttribute('aria-expanded', String(details.open));
      region.setAttribute('aria-hidden', String(!details.open));
    };
    details.addEventListener('toggle', syncDisclosure);
    // Un clic dans la liste (ou sa barre de défilement) conserve son ouverture.
    document.addEventListener('click', event => {
      if (details.open && !details.contains(event.target)) {
        const restoreFocus = details.contains(document.activeElement);
        details.open = false;
        syncDisclosure();
        if (restoreFocus) summary.focus({preventScroll: true});
      }
    });
    details.addEventListener('keydown', event => {
      if (event.key !== 'Escape' || event.defaultPrevented || !details.open) return;
      event.preventDefault();
      details.open = false;
      syncDisclosure();
      summary.focus({preventScroll: true});
    });
    window.addEventListener('pageshow', syncDisclosure);
    syncDisclosure();
  });
  document.querySelectorAll('[data-gallery-actions]').forEach(galleryForm => {
    let pending = false;
    galleryForm.addEventListener('submit', event => {
      if (pending) { event.preventDefault(); return; }
      if (event.submitter?.value === 'delete' && !window.confirm('Supprimer cette photo du produit ?')) {
        event.preventDefault(); return;
      }
      pending = true;
      galleryForm.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => { pending = false; galleryForm.removeAttribute('aria-busy'); });
  });
  const form = document.querySelector('.admin-product-form');
  if (!form) return;
  const publication = form.querySelector('#product-status');
  const submit = form.querySelector('[data-product-submit]');
  if (publication && submit && form.querySelector('[name=photo_batch]')) {
    const syncPublication = () => { submit.textContent = publication.value === 'actif' ? 'Publier le produit' : 'Créer le brouillon'; };
    publication.addEventListener('change', syncPublication);
    window.addEventListener('pageshow', syncPublication);
    syncPublication();
  }
  const skuMode = form.querySelector('[data-sku-mode]');
  if (skuMode) {
    const input = skuMode.querySelector('[name="sku"]');
    const manualPanel = skuMode.querySelector('[data-sku-manual]');
    const autoHelp = skuMode.querySelector('[data-sku-auto-help]');
    const syncSkuMode = () => {
      const manual = skuMode.querySelector('[value="manual"]').checked;
      manualPanel.hidden = !manual;
      autoHelp.hidden = manual;
      input.disabled = !manual;
      input.required = manual;
    };
    skuMode.addEventListener('change', syncSkuMode);
    window.addEventListener('pageshow', syncSkuMode);
    syncSkuMode();
  }
  const feedback = form.querySelector('[data-product-form-feedback]');
  form.querySelectorAll('[data-select-control]').forEach(select => {
    window.SelectControl?.enhance(select);
    const trigger = document.getElementById(select.id + '-trigger');
    if (select.getAttribute('aria-invalid') === 'true') trigger?.setAttribute('aria-invalid', 'true');
    // Un select amélioré est masqué : guider le focus vers son contrôle visible.
    select.addEventListener('invalid', event => {
      if (!select.hidden) return;
      event.preventDefault();
      const trigger = document.getElementById(select.id + '-trigger');
      trigger?.setAttribute('aria-invalid', 'true');
      trigger?.focus();
      if (feedback) { feedback.hidden = false; feedback.textContent = 'Choisissez une valeur pour les champs obligatoires.'; }
    });
    select.addEventListener('change', () => {
      if (select.getAttribute('aria-invalid') !== 'true') document.getElementById(select.id + '-trigger')?.removeAttribute('aria-invalid');
      if (feedback) feedback.hidden = true;
    });
  });
  let submitting = false;
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    if (submitting) { event.preventDefault(); return; }
    submitting = true;
    form.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => { submitting = false; form.removeAttribute('aria-busy'); });
})();
