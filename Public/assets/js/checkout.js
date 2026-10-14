// Interactions de livraison uniquement : le serveur reste la source des tarifs.
(() => {
  const page = document.querySelector('[data-checkout-page]');
  if (!page) return;
  const form = page.querySelector('[data-checkout-form]');
  const summary = page.querySelector('[data-checkout-summary]');
  const message = page.querySelector('[data-checkout-message]');
  const saveOptions = page.querySelector('[data-save-options]');
  const fields = [...page.querySelectorAll('[data-address-fields] input, [data-address-fields] textarea, [data-address-fields] select')];
  const initialValues = new Map(fields.map((field) => [field.name, field.value]));
  const initialSummary = summary.innerHTML;
  const zone = form.elements.namedItem('id_zone_livraison');
  const submit = form.querySelector('[type="submit"]');
  let requestController;
  let pending = false;

  const showMessage = (text) => { message.textContent = text; };
  const clearErrors = () => {
    page.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    fields.forEach((field) => field.removeAttribute('aria-invalid'));
  };
  const readResponse = async (response) => {
    let payload;
    try { payload = await response.json(); }
    catch { throw new Error('Réponse indisponible. Rechargez la page et réessayez.'); }
    if (!response.ok) {
      const error = new Error(payload.error || 'Impossible de valider la livraison.');
      error.fields = payload.errors || {};
      throw error;
    }
    return payload;
  };

  // Annuler les anciennes lectures evite qu'un tarif arrive apres un autre choix.
  const updateSummary = async (zoneId) => {
    requestController?.abort();
    summary.innerHTML = initialSummary;
    summary.removeAttribute('aria-busy');
    if (!zoneId) return;
    const controller = new AbortController();
    requestController = controller;
    summary.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(`/api/checkout/recapitulatif?zone=${encodeURIComponent(zoneId)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
      const payload = await readResponse(response);
      summary.innerHTML = payload.html;
      window.lucide?.createIcons();
    } catch (error) {
      if (error.name !== 'AbortError') showMessage(error.message || 'Impossible de calculer la livraison.');
    } finally {
      if (requestController === controller) summary.removeAttribute('aria-busy');
    }
  };

  form.addEventListener('change', (event) => {
    if (pending) return;
    clearErrors();
    showMessage('');
    if (event.target.name === 'address_id') {
      const saved = event.target.dataset.address ? JSON.parse(event.target.dataset.address) : null;
      fields.forEach((field) => {
        field.value = saved ? (saved[field.name] ?? '') : initialValues.get(field.name);
        if (field.tagName === 'SELECT') field.disabled = Boolean(saved) || field.options.length === 1;
        else field.readOnly = Boolean(saved);
      });
      saveOptions.hidden = Boolean(saved);
      saveOptions.querySelectorAll('input').forEach((input) => { input.disabled = Boolean(saved); });
      updateSummary(zone.value);
    } else if (event.target === zone) updateSummary(zone.value);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pending || submit.disabled) return;
    pending = true;
    clearErrors();
    showMessage('Validation des informations…');
    const data = new FormData(form);
    const controls = [...form.elements].filter((element) => 'disabled' in element);
    const disabledState = new Map(controls.map((element) => [element, element.disabled]));
    controls.forEach((element) => { element.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    requestController?.abort();
    summary.removeAttribute('aria-busy');
    let savedRadio;
    try {
      const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
      const payload = await readResponse(response);
      summary.innerHTML = payload.summary.html;
      window.lucide?.createIcons();
      showMessage(payload.saved ? 'Adresse enregistrée. Les informations de livraison sont validées ; le paiement sera disponible dans une prochaine étape.' : 'Informations de livraison validées. Le paiement sera disponible dans une prochaine étape.');
      if (payload.saved) {
        // Reutiliser l'adresse creee empeche un second clic de la dupliquer.
        const label = document.createElement('label');
        label.className = 'checkout-saved-address';
        savedRadio = document.createElement('input');
        savedRadio.type = 'radio'; savedRadio.name = 'address_id'; savedRadio.value = String(payload.addressId);
        savedRadio.dataset.address = JSON.stringify(payload.address);
        const text = document.createElement('span');
        text.textContent = `${payload.address.commune} — ${payload.address.adresse}`;
        label.append(savedRadio, text);
        form.querySelector('.checkout-address-choice').append(label);
      }
    } catch (error) {
      showMessage(error.message || 'Connexion indisponible. Réessayez.');
      Object.entries(error.fields || {}).forEach(([name, text]) => {
        const input = form.elements.namedItem(name);
        input?.setAttribute('aria-invalid', 'true');
        const errorNode = [...page.querySelectorAll('[data-error-for]')].find((node) => node.dataset.errorFor === name);
        if (errorNode) errorNode.textContent = text;
      });
    } finally {
      pending = false;
      controls.forEach((element) => { element.disabled = disabledState.get(element); });
      form.removeAttribute('aria-busy');
      if (savedRadio) {
        savedRadio.checked = true;
        fields.forEach((field) => { if (field.tagName === 'SELECT') field.disabled = true; else field.readOnly = true; });
        saveOptions.hidden = true;
        saveOptions.querySelectorAll('input').forEach((input) => { input.disabled = true; });
      }
      message.focus();
    }
  });
})();
